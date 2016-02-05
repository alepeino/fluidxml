<?php

namespace FluidXml;

/**
 * @method array array()
 */
class FluidContext implements FluidInterface, \ArrayAccess, \Iterator
{
        use NewableTrait,
            FluidSaveTrait,
            ReservedCallTrait,          // For compatibility with PHP 5.6.
            ReservedCallStaticTrait;    // For compatibility with PHP 5.6.

        private $document;
        private $handler;
        private $nodes = [];
        private $seek = 0;

        public function __construct($document, $handler, $context)
        {
                $this->document = $document;
                $this->handler  = $handler;

                if (! \is_array($context) && ! $context instanceof \Traversable) {
                        // DOMDocument, DOMElement and DOMNode are not iterable.
                        // DOMNodeList and FluidContext are iterable.
                        $context = [ $context ];
                }

                foreach ($context as $n) {
                        if (! $n instanceof \DOMNode) {
                                throw new \Exception('Node type not recognized.');
                        }

                        $this->nodes[] = $n;
                }
        }

        // \ArrayAccess interface.
        public function offsetSet($offset, $value)
        {
                // if (\is_null($offset)) {
                //         $this->nodes[] = $value;
                // } else {
                //         $this->nodes[$offset] = $value;
                // }
                throw new \Exception('Setting a context element is not allowed.');
        }

        // \ArrayAccess interface.
        public function offsetExists($offset)
        {
                return isset($this->nodes[$offset]);
        }

        // \ArrayAccess interface.
        public function offsetUnset($offset)
        {
                // unset($this->nodes[$offset]);
                \array_splice($this->nodes, $offset, 1);
        }

        // \ArrayAccess interface.
        public function offsetGet($offset)
        {
                if (isset($this->nodes[$offset])) {
                        return $this->nodes[$offset];
                }

                return null;
        }

        // \Iterator interface.
        public function rewind()
        {
                $this->seek = 0;
        }

        // \Iterator interface.
        public function current()
        {
                return $this->nodes[$this->seek];
        }

        // \Iterator interface.
        public function key()
        {
                return $this->seek;
        }

        // \Iterator interface.
        public function next()
        {
                ++$this->seek;
        }

        // \Iterator interface.
        public function valid()
        {
                return isset($this->nodes[$this->seek]);
        }

        public function length()
        {
                return \count($this->nodes);
        }

        // Alias of ->length().
        public function size()
        {
                return $this->length();
        }

        public function query(...$query)
        {
                if (\is_array($query[0])) {
                        $query = $query[0];
                }

                $results = [];

                $xp = $this->document->xpath;

                foreach ($this->nodes as $n) {
                        foreach ($query as $q) {
                                $q = $this->resolveQuery($q);

                                // Returns a DOMNodeList.
                                $res = $xp->query($q, $n);

                                // Algorithm 1:
                                // $results = \array_merge($results, \iterator_to_array($res));

                                // Algorithm 2:
                                // It is faster than \iterator_to_array and a lot faster
                                // than \iterator_to_array + \array_merge.
                                foreach ($res as $r) {
                                        $results[] = $r;
                                }

                                // Algorithm 3:
                                // for ($i = 0, $l = $res->length; $i < $l; ++$i) {
                                //         $results[] = $res->item($i);
                                // }
                        }
                }

                // Performing over multiple sibling nodes a query that ascends
                // the xpath, relative (../..) or absolute (//), returns identical
                // matching results that must be collapsed in an unique result
                // otherwise a subsequent operation is performed multiple times.
                $results = $this->filterQueryResults($results);

                return $this->newContext($results);
        }

        public function __invoke(...$query)
        {
                return $this->query(...$query);
        }

        public function times($times, callable $fn = null)
        {
                if ($fn === null) {
                        return new FluidRepeater($this->document, $this->handler, $this, $times);
                }

                for ($i = 0; $i < $times; ++$i) {
                        $this->callfn($fn, [$this, $i]);
                }

                return $this;
        }

        public function each(callable $fn)
        {
                foreach ($this->nodes as $i => $n) {
                        $cx = $this->newContext($n);

                        $this->callfn($fn, [$cx, $i, $n]);
                }

                return $this;
        }

        public function filter(callable $fn)
        {
                $nodes = [];

                foreach ($this->nodes as $i => $n) {
                        $cx = $this->newContext($n);

                        $ret = $this->callfn($fn, [$cx, $i, $n]);

                        if ($ret !== false) {
                                $nodes[] = $n;
                        }
                }

                return $this->newContext($nodes);
        }

        // addChild($child, $value?, $attributes? = [], $switchContext? = false)
        public function addChild($child, ...$optionals)
        {
                return $this->handler->insertElement($this->nodes, $child, $optionals, function ($parent, $element) {
                        return $parent->appendChild($element);
                }, $this);
        }

        // Alias of ->addChild().
        public function add($child, ...$optionals)
        {
                return $this->addChild($child, ...$optionals);
        }

        public function prependSibling($sibling, ...$optionals)
        {
                return $this->handler->insertElement($this->nodes, $sibling, $optionals, function ($sibling, $element) {
                        return $sibling->parentNode->insertBefore($element, $sibling);
                }, $this);
        }

        // Alias of ->prependSibling().
        public function prepend($sibling, ...$optionals)
        {
                return $this->prependSibling($sibling, ...$optionals);
        }

        // Alias of ->prependSibling().
        public function insertSiblingBefore($sibling, ...$optionals)
        {
                return $this->prependSibling($sibling, ...$optionals);
        }

        public function appendSibling($sibling, ...$optionals)
        {
                return $this->handler->insertElement($this->nodes, $sibling, $optionals, function ($sibling, $element) {
                        // If ->nextSibling is null, $element is simply appended as last sibling.
                        return $sibling->parentNode->insertBefore($element, $sibling->nextSibling);
                }, $this);
        }

        // Alias of ->appendSibling().
        public function append($sibling, ...$optionals)
        {
                return $this->appendSibling($sibling, ...$optionals);
        }

        // Arguments can be in the form of:
        // setAttribute($name, $value)
        // setAttribute(['name' => 'value', ...])
        public function setAttribute(...$arguments)
        {
                // Default case is:
                // [ 'name' => 'value', ... ]
                $attrs = $arguments[0];

                // If the first argument is not an array,
                // the user has passed two arguments:
                // 1. is the attribute name
                // 2. is the attribute value
                if (! \is_array($arguments[0])) {
                        $attrs = [$arguments[0] => $arguments[1]];
                }

                foreach ($this->nodes as $n) {
                        foreach ($attrs as $k => $v) {
                                // Algorithm 1:
                                $n->setAttribute($k, $v);

                                // Algorithm 2:
                                // $n->setAttributeNode(new \DOMAttr($k, $v));

                                // Algorithm 3:
                                // $n->appendChild(new \DOMAttr($k, $v));

                                // Algorithm 2 and 3 have a different behaviour
                                // from Algorithm 1.
                                // The attribute is still created or setted, but
                                // changing the value of an existing attribute
                                // changes even the order of that attribute
                                // in the attribute list.
                        }
                }

                return $this;
        }

        // Alias of ->setAttribute().
        public function attr(...$arguments)
        {
                return $this->setAttribute(...$arguments);
        }

        public function setText($text)
        {
                foreach ($this->nodes as $n) {
                        // Algorithm 1:
                        $n->nodeValue = $text;

                        // Algorithm 2:
                        // foreach ($n->childNodes as $c) {
                        //         $n->removeChild($c);
                        // }
                        // $n->appendChild(new \DOMText($text));

                        // Algorithm 3:
                        // foreach ($n->childNodes as $c) {
                        //         $n->replaceChild(new \DOMText($text), $c);
                        // }
                }

                return $this;
        }

        // Alias of ->setText().
        public function text($text)
        {
                return $this->setText($text);
        }

        public function addText($text)
        {
                foreach ($this->nodes as $n) {
                        $n->appendChild(new \DOMText($text));
                }

                return $this;
        }

        public function setCdata($text)
        {
                foreach ($this->nodes as $n) {
                        $n->nodeValue = '';
                        $n->appendChild(new \DOMCDATASection($text));
                }

                return $this;
        }

        // Alias of ->setCdata().
        public function cdata($text)
        {
                return $this->setCdata($text);
        }

        public function addCdata($text)
        {
                foreach ($this->nodes as $n) {
                        $n->appendChild(new \DOMCDATASection($text));
                }

                return $this;
        }

        public function setComment($text)
        {
                foreach ($this->nodes as $n) {
                        $n->nodeValue = '';
                        $n->appendChild(new \DOMComment($text));
                }

                return $this;
        }

        // Alias of ->setComment().
        public function comment($text)
        {
                return $this->setComment($text);
        }

        public function addComment($text)
        {
                foreach ($this->nodes as $n) {
                        $n->appendChild(new \DOMComment($text));
                }

                return $this;
        }

        public function remove(...$query)
        {
                // Arguments can be empty, a string or an array of strings.

                if (empty($query)) {
                        // The user has requested to remove the nodes of this context.
                        $targets = $this->nodes;
                } else {
                        $targets = $this->query(...$query);
                }

                foreach ($targets as $t) {
                        $t->parentNode->removeChild($t);
                }

                return $this;
        }

        public function dom()
        {
                return $this->document->dom;
        }

        // This method should be called 'array',
        // but for compatibility with PHP 5.6
        // it is shadowed by the __call() method.
        public function array_()
        {
                return $this->nodes;
        }

        public function __toString()
        {
                return $this->xml();
        }

        public function xml($strip = false)
        {
                return FluidHelper::domnodesToString($this->nodes);
        }

        public function html($strip = false)
        {
                return FluidHelper::domnodesToString($this->nodes, true);
        }

        protected function newContext(&$context)
        {
                return new FluidContext($this->document, $this->handler, $context);
        }

        protected function resolveQuery($query)
        {
                if ($query[0] === '/'
                    || $query === '.'
                    || ($query[0] === '.' && $query[1] === '/')
                    || ($query[0] === '.' && $query[1] === '.' && $query[2] === '/')) {
                        return $query;
                }

                return CssTranslator::xpath($query);
        }

        protected function filterQueryResults(&$results)
        {
                $set = [];

                foreach ($results as $r) {
                        $found = false;

                        foreach ($set as $u) {
                                $found = ($r === $u) || $found;
                        }

                        if (! $found) {
                                $set[] = $r;
                        }
                }

                return $set;
        }

        protected function callfn($fn, $args)
        {
                if ($fn instanceof \Closure) {
                        $bind = \array_shift($args);

                        $fn = $fn->bindTo($bind);

                        // It is faster than \call_user_func.
                        return $fn(...$args);
                }

                return \call_user_func($fn, ...$args);
        }
}
