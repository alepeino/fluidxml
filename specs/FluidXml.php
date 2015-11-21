<?php

require_once __DIR__ . DIRECTORY_SEPARATOR . ".common.php";

require_once 'FluidXml.php';


use FluidNamespace as Name;


describe('fluidxml', function() {
        it('should return a new FluidXml instance', function() {
                $xml = fluidxml();

                $actual = $xml;
                assert_is_a($actual, FluidXml::class);
        });
});

describe('FluidXml', function() {
        it('should be an UTF-8 XML-1.0 document with one default root element', function() {
                $xml = new FluidXml();

                $expected = "<doc/>";
                assert_equal_xml($xml, $expected);
        });

        it('should be an UTF-8 XML-1.0 document with one custom root element', function() {
                $xml = new FluidXml(['root' => 'document']);

                $expected = "<document/>";
                assert_equal_xml($xml, $expected);
        });

        it('should be an UTF-8 XML-1.0 document with no root element', function() {
                $xml = new FluidXml(['root' => null]);

                $expected = "";
                assert_equal_xml($xml, $expected);
        });

        it('should be an UTF-8 XML-1.0 document with a stylesheet', function() {
                $xml = new FluidXml(['stylesheet' => 'http://servo-php.org/fluidxml']);

                $expected = "<?xml-stylesheet type=\"text/xsl\" encoding=\"UTF-8\" indent=\"yes\" href=\"http://servo-php.org/fluidxml\"?>\n<doc/>";
                assert_equal_xml($xml, $expected);

                $xml = new FluidXml(['root' => null, 'stylesheet' => 'http://servo-php.org/fluidxml']);
                $expected = "<?xml-stylesheet type=\"text/xsl\" encoding=\"UTF-8\" indent=\"yes\" href=\"http://servo-php.org/fluidxml\"?>";
                assert_equal_xml($xml, $expected);
        });

        describe('.dom', function() {
                it('should return the DOMDocument of the document', function() {
                        $xml = new FluidXml();

                        $actual = $xml->dom();
                        assert_is_a($actual, \DOMDocument::class);
                });
        });

        describe('.query', function() {
                it('should return the root nodes of the document', function() {
                        // XPATH: /*
                        $xml = new FluidXml();
                        $cx = $xml->query('/*');

                        $actual   = $cx[0]->nodeName;
                        $expected = 'doc';
                        assert($actual === $expected, __($actual, $expected));

                        $xml->appendSibling('meta');
                        $cx = $xml->query('/*');

                        $actual   = $cx[0]->nodeName;
                        $expected = 'doc';
                        assert($actual === $expected, __($actual, $expected));

                        $actual   = $cx[1]->nodeName;
                        $expected = 'meta';
                        assert($actual === $expected, __($actual, $expected));
                });

                it('should support chained relative queries', function() {
                        // XPATH: //child subchild
                        $xml = new FluidXml();
                        $cx = $xml->appendChild('html', true);
                        $cx->appendChild(['head','body']);
                        $cx = $cx->query('body');

                        $actual   = $cx[0]->nodeName;
                        $expected = 'body';
                        assert($actual === $expected, __($actual, $expected));

                        $xml = new FluidXml();
                        $xml->appendChild('html', true)->appendChild(['head','body']);
                        $cx = $xml->query('/doc/html')->query('head');

                        $actual   = $cx[0]->nodeName;
                        $expected = 'head';
                        assert($actual === $expected, __($actual, $expected));
                });

                it('should query the root of the document from a sub query', function() {
                        // XPATH: //child/subchild //child
                        $xml = new FluidXml();
                        $xml->appendChild('html', true)
                            ->appendChild(['head','body']);
                        $cx = $xml->query('/doc/html/body')
                                  ->appendChild('h1')
                                  ->query('/doc/html/head');

                        $actual   = $cx[0]->nodeName;
                        $expected = 'head';
                        assert($actual === $expected, __($actual, $expected));
                });

                it('should perform relative queries ascending the DOM tree', function() {
                        // XPATH: //child/subchild ../..
                        $xml = new FluidXml();
                        $xml->appendChild('html', true)
                            ->appendChild(['head','body'], true)
                            ->query('../body')
                            ->appendChild('h1')
                            ->query('../..')
                            ->appendChild('extra');

                        $expected = "<doc>\n"       .
                                    "  <html>\n"    .
                                    "    <head/>\n" .
                                    "    <body>\n"  .
                                    "      <h1/>\n" .
                                    "    </body>\n" .
                                    "  </html>\n"   .
                                    "  <extra/>\n"  .
                                    "</doc>";
                        assert_equal_xml($xml, $expected);
                });
        });

        describe('.appendChild', function() {
                it('should add a child', function() {
                        $xml = new FluidXml();
                        $xml->appendChild('child1')
                            ->appendChild('child2')
                            ->appendChild('parent', true)
                            ->appendChild('child3')
                            ->appendChild('child4');

                        $expected = "<doc>\n"           .
                                    "  <child1/>\n"     .
                                    "  <child2/>\n"     .
                                    "  <parent>\n"      .
                                    "    <child3/>\n"   .
                                    "    <child4/>\n"   .
                                    "  </parent>\n"     .
                                    "</doc>";
                        assert_equal_xml($xml, $expected);
                });

                it('should add many children', function() {
                        $xml = new FluidXml();
                        $xml->appendChild(['child1', 'child2'])
                            ->appendChild('parent', true)
                            ->appendChild(['child3', 'child4']);

                        $expected = "<doc>\n"           .
                                    "  <child1/>\n"     .
                                    "  <child2/>\n"     .
                                    "  <parent>\n"      .
                                    "    <child3/>\n"   .
                                    "    <child4/>\n"   .
                                    "  </parent>\n"     .
                                    "</doc>";
                        assert_equal_xml($xml, $expected);
                });

                it('should add many children recursively', function() {
                        $xml = new FluidXml();
                        $xml->appendChild(['child1'=>['child11'=>['child111', 'child112'=>'value112'], 'child12'=>'value12'],
                                           'child2'=>['child21', 'child22'=>['child221', 'child222']]])
                            ->appendChild('parent', true)
                            ->appendChild(['child3'=>['child31'=>['child311', 'child312'=>'value312'], 'child32'=>'value32'],
                                           'child4'=>['child41', 'child42'=>['child421', 'child422']]]);

                        $expected = <<<EOF
<doc>
  <child1>
    <child11>
      <child111/>
      <child112>value112</child112>
    </child11>
    <child12>value12</child12>
  </child1>
  <child2>
    <child21/>
    <child22>
      <child221/>
      <child222/>
    </child22>
  </child2>
  <parent>
    <child3>
      <child31>
        <child311/>
        <child312>value312</child312>
      </child31>
      <child32>value32</child32>
    </child3>
    <child4>
      <child41/>
      <child42>
        <child421/>
        <child422/>
      </child42>
    </child4>
  </parent>
</doc>
EOF;

                        assert_equal_xml($xml, $expected);
                });

                it('should add a child with a value', function() {
                        $xml = new FluidXml();
                        $xml->appendChild(['child1' => 'value1'])
                            ->appendChild('child2', 'value2')
                            ->appendChild('parent', true)
                            ->appendChild(['child3' => 'value3'])
                            ->appendChild('child4', 'value4');

                        $expected = "<doc>\n"           .
                                    "  <child1>value1</child1>\n"     .
                                    "  <child2>value2</child2>\n"     .
                                    "  <parent>\n"      .
                                    "    <child3>value3</child3>\n"   .
                                    "    <child4>value4</child4>\n"   .
                                    "  </parent>\n"     .
                                    "</doc>";
                        assert_equal_xml($xml, $expected);
                });

                it('should add many children with a value', function() {
                        $xml = new FluidXml();
                        $xml->appendChild(['child1' => 'value1', 'child2' => 'value2'])
                             ->appendChild('parent', true)
                             ->appendChild(['child3' => 'value3', 'child4' => 'value4']);

                        $expected = "<doc>\n"           .
                                    "  <child1>value1</child1>\n"     .
                                    "  <child2>value2</child2>\n"     .
                                    "  <parent>\n"      .
                                    "    <child3>value3</child3>\n"   .
                                    "    <child4>value4</child4>\n"   .
                                    "  </parent>\n"     .
                                    "</doc>";
                        assert_equal_xml($xml, $expected);

                        $xml = new FluidXml();
                        $xml->appendChild([ 'child', ['child'], ['child' => 'value1'], ['child' => 'value2'] ])
                             ->appendChild('parent', true)
                             ->appendChild([ 'child', ['child'], ['child' => 'value3'], ['child' => 'value4'] ]);

                        $expected = "<doc>\n"           .
                                    "  <child/>\n"      .
                                    "  <child/>\n"      .
                                    "  <child>value1</child>\n"         .
                                    "  <child>value2</child>\n"         .
                                    "  <parent>\n"      .
                                    "    <child/>\n"      .
                                    "    <child/>\n"      .
                                    "    <child>value3</child>\n"         .
                                    "    <child>value4</child>\n"         .
                                    "  </parent>\n"     .
                                    "</doc>";
                        assert_equal_xml($xml, $expected);
                });

                it('should add a child with some attributes', function() {
                        $xml = new FluidXml();
                        $xml->appendChild('child1', ['class' => 'Class attr', 'id' => 'Id attr1'])
                            ->appendChild('parent', true)
                            ->appendChild('child2', ['class' => 'Class attr', 'id' => 'Id attr2']);

                        $expected = "<doc>\n"   .
                                    "  <child1 class=\"Class attr\" id=\"Id attr1\"/>\n"        .
                                    "  <parent>\n"      .
                                    "    <child2 class=\"Class attr\" id=\"Id attr2\"/>\n"      .
                                    "  </parent>\n"     .
                                    "</doc>";
                        assert_equal_xml($xml, $expected);
                });

                it('should add many children with some attributes both', function() {
                        $xml = new FluidXml();
                        $xml->appendChild(['child1', 'child2'], ['class' => 'Class attr', 'id' => 'Id attr1'])
                            ->appendChild('parent', true)
                            ->appendChild(['child3', 'child4'], ['class' => 'Class attr', 'id' => 'Id attr2']);

                        $expected = "<doc>\n"   .
                                    "  <child1 class=\"Class attr\" id=\"Id attr1\"/>\n"       .
                                    "  <child2 class=\"Class attr\" id=\"Id attr1\"/>\n"       .
                                    "  <parent>\n"      .
                                    "    <child3 class=\"Class attr\" id=\"Id attr2\"/>\n"      .
                                    "    <child4 class=\"Class attr\" id=\"Id attr2\"/>\n"      .
                                    "  </parent>\n"     .
                                    "</doc>";
                        assert_equal_xml($xml, $expected);
                });

                it('should switch context', function() {
                        $xml = new FluidXml();

                        $actual = $xml->appendChild('child', true);
                        assert_is_a($actual, FluidContext::class);

                        $actual = $xml->appendChild('child', 'value', true);
                        assert_is_a($actual, FluidContext::class);

                        $actual = $xml->appendChild(['child1', 'child2'], true);
                        assert_is_a($actual, FluidContext::class);

                        $actual = $xml->appendChild(['child1' => 'value1', 'child2' => 'value2'], true);
                        assert_is_a($actual, FluidContext::class);

                        $actual = $xml->appendChild('child', ['attr' => 'value'], true);
                        assert_is_a($actual, FluidContext::class);

                        $actual = $xml->appendChild(['child1', 'child2'], ['attr' => 'value'], true);
                        assert_is_a($actual, FluidContext::class);
                });
        });

        describe('.prependSibling', function() {
                it('should add more than one root node to a document with one root node', function() {
                        $xml = new FluidXml();
                        $xml->prependSibling('meta');
                        $xml->prependSibling('extra');
                        $cx = $xml->query('/*');

                        $actual   = $cx[0]->nodeName;
                        $expected = 'extra';
                        assert($actual === $expected, __($actual, $expected));

                        $actual   = $cx[1]->nodeName;
                        $expected = 'meta';
                        assert($actual === $expected, __($actual, $expected));

                        $actual   = $cx[2]->nodeName;
                        $expected = 'doc';
                        assert($actual === $expected, __($actual, $expected));
                });

                it('should add more than one root node to a document with no root node', function() {
                        $xml = new FluidXml(['root'=>null]);
                        $xml->prependSibling('meta');
                        $xml->prependSibling('extra');
                        $cx = $xml->query('/*');

                        $actual   = $cx[0]->nodeName;
                        $expected = 'extra';
                        assert($actual === $expected, __($actual, $expected));

                        $actual   = $cx[1]->nodeName;
                        $expected = 'meta';
                        assert($actual === $expected, __($actual, $expected));
                });

                it('should insert a sibling node before a node', function() {
                        $xml = new FluidXml();
                        $xml->appendChild('parent', true)
                            ->prependSibling('sibling1')
                            ->prependSibling('sibling2');

                        $expected = "<doc>\n"      .
                                    "  <sibling1/>\n" .
                                    "  <sibling2/>\n" .
                                    "  <parent/>\n" .
                                    "</doc>";
                        assert_equal_xml($xml, $expected);
                });
        });

        describe('.appendSibling', function() {
                it('should add more than one root node to a document with one root node', function() {
                        $xml = new FluidXml();
                        $xml->appendSibling('meta');
                        $xml->appendSibling('extra');
                        $cx = $xml->query('/*');

                        $actual   = $cx[0]->nodeName;
                        $expected = 'doc';
                        assert($actual === $expected, __($actual, $expected));

                        $actual   = $cx[1]->nodeName;
                        $expected = 'extra';
                        assert($actual === $expected, __($actual, $expected));

                        $actual   = $cx[2]->nodeName;
                        $expected = 'meta';
                        assert($actual === $expected, __($actual, $expected));
                });

                it('should add more than one root node to a document with no root node', function() {
                        $xml = new FluidXml(['root'=>null]);
                        $xml->appendSibling('meta');
                        $xml->appendSibling('extra');
                        $cx = $xml->query('/*');

                        $actual   = $cx[0]->nodeName;
                        $expected = 'meta';
                        assert($actual === $expected, __($actual, $expected));

                        $actual   = $cx[1]->nodeName;
                        $expected = 'extra';
                        assert($actual === $expected, __($actual, $expected));
                });

                it('should insert a sibling node after a node', function() {
                        $xml = new FluidXml();
                        $xml->appendChild('parent', true)
                            ->appendSibling('sibling1')
                            ->appendSibling('sibling2');

                        $expected = "<doc>\n"      .
                                    "  <parent/>\n" .
                                    "  <sibling2/>\n" .
                                    "  <sibling1/>\n" .
                                    "</doc>";
                        assert_equal_xml($xml, $expected);
                });
        });

        describe('.appendXml', function() {
                it('should populate the document with an xml document', function() {
                        $xml = new FluidXml(['root'=>null]);
                        $xml->appendXml('<root1/><root2/>', true);

                        $expected = "<root1/>\n" .
                                    "<root2/>";
                        assert_equal_xml($xml, $expected);
                });

                it('should add to the document an xml document', function() {
                        $xml = new FluidXml();
                        $xml->appendXml('<child1/><child2/>');

                        $expected = "<doc>\n"      .
                                    "  <child1/>\n" .
                                    "  <child2/>\n" .
                                    "</doc>";
                        assert_equal_xml($xml, $expected);
                });

                it('should add to a node an xml document', function() {
                        $xml = new FluidXml();
                        $xml->appendChild('parent', true)
                            ->appendXml('<child1/><child2/>');

                        $expected = "<doc>\n"         .
                                    "  <parent>\n"    .
                                    "    <child1/>\n" .
                                    "    <child2/>\n" .
                                    "  </parent>\n"   .
                                    "</doc>";
                        assert_equal_xml($xml, $expected);
                });
        });

        describe('.setAttribute', function() {
                it('should set the attributes of the root node', function() {
                        $xml = new FluidXml();
                        $xml->setAttribute('attr1', 'Attr1 Value')
                            ->setAttribute('attr2', 'Attr2 Value');

                        $expected = "<doc attr1=\"Attr1 Value\" attr2=\"Attr2 Value\"/>";
                        assert_equal_xml($xml, $expected);

                        $xml = new FluidXml();
                        $xml->setAttribute(['attr1' => 'Attr1 Value',
                                            'attr2' => 'Attr2 Value']);

                        $expected = "<doc attr1=\"Attr1 Value\" attr2=\"Attr2 Value\"/>";
                        assert_equal_xml($xml, $expected);
                });

                it('should change the attributes of the root node', function() {
                        $xml = new FluidXml();
                        $xml->setAttribute('attr1', 'Attr1 Value')
                            ->setAttribute('attr2', 'Attr2 Value');

                        $xml->setAttribute('attr2', 'Attr2 New Value');

                        $expected = "<doc attr1=\"Attr1 Value\" attr2=\"Attr2 New Value\"/>";
                        assert_equal_xml($xml, $expected);

                        $xml->setAttribute('attr1', 'Attr1 New Value');

                        $expected = "<doc attr1=\"Attr1 New Value\" attr2=\"Attr2 New Value\"/>";
                        assert_equal_xml($xml, $expected);
                });

                it('should set the attributes of a node', function() {
                        $xml = new FluidXml();
                        $xml->appendChild('child', true)
                            ->setAttribute('attr1', 'Attr1 Value')
                            ->setAttribute('attr2', 'Attr2 Value');

                        $expected = "<doc>\n"   .
                                    "  <child attr1=\"Attr1 Value\" attr2=\"Attr2 Value\"/>\n" .
                                    "</doc>";
                        assert_equal_xml($xml, $expected);

                        $xml = new FluidXml();
                        $xml->appendChild('child', true)
                            ->setAttribute(['attr1' => 'Attr1 Value',
                                            'attr2' => 'Attr2 Value']);

                        $expected = "<doc>\n"   .
                                    "  <child attr1=\"Attr1 Value\" attr2=\"Attr2 Value\"/>\n" .
                                    "</doc>";
                        assert_equal_xml($xml, $expected);
                });

                it('should change the attributes of a node', function() {
                        $xml = new FluidXml();
                        $xml->appendChild('child', true)
                            ->setAttribute('attr1', 'Attr1 Value')
                            ->setAttribute('attr2', 'Attr2 Value')
                            ->setAttribute('attr2', 'Attr2 New Value');

                        $expected = "<doc>\n"   .
                                    "  <child attr1=\"Attr1 Value\" attr2=\"Attr2 New Value\"/>\n" .
                                    "</doc>";
                        assert_equal_xml($xml, $expected);

                        $xml = new FluidXml();
                        $xml->appendChild('child', true)
                            ->setAttribute(['attr1' => 'Attr1 Value',
                                            'attr2' => 'Attr2 Value'])
                            ->setAttribute('attr1', 'Attr1 New Value');

                        $expected = "<doc>\n"   .
                                    "  <child attr1=\"Attr1 New Value\" attr2=\"Attr2 Value\"/>\n" .
                                    "</doc>";
                        assert_equal_xml($xml, $expected);
                });
        });

        describe('.appendText', function() {
                it('should add text to the root node', function() {
                        $xml = new FluidXml();
                        $xml->appendText('Document Text First Line');

                        $expected = "<doc>Document Text First Line</doc>";
                        assert_equal_xml($xml, $expected);

                        $xml->appendText('Document Text Second Line');

                        $expected = "<doc>Document Text First LineDocument Text Second Line</doc>";
                        assert_equal_xml($xml, $expected);
                });

                it('should add text to a node', function() {
                        $xml = new FluidXml();
                        $cx = $xml->appendChild('p', true);
                        $cx->appendText('Document Text First Line');

                        $expected = "<doc>\n" .
                                    "  <p>Document Text First Line</p>\n" .
                                    "</doc>";
                        assert_equal_xml($xml, $expected);

                        $cx->appendText('Document Text Second Line');

                        $expected = "<doc>\n" .
                                    "  <p>Document Text First LineDocument Text Second Line</p>\n" .
                                    "</doc>";
                        assert_equal_xml($xml, $expected);
                });
        });

        describe('.setText', function() {
                it('should set the text of the root node', function() {
                        $xml = new FluidXml();
                        $xml->setText('Document Text');

                        $expected = "<doc>Document Text</doc>";
                        assert_equal_xml($xml, $expected);
                });

                it('should change the text of the root node', function() {
                        $xml = new FluidXml();
                        $xml->setText('Document Text');

                        $expected = "<doc>Document Text</doc>";
                        assert_equal_xml($xml, $expected);

                        $xml->setText('Document New Text');

                        $expected = "<doc>Document New Text</doc>";
                        assert_equal_xml($xml, $expected);
                });

                it('should set the text of a node', function() {
                        $xml = new FluidXml();
                        $cx = $xml->appendChild('p', true);
                        $cx->setText('Document Text');

                        $expected = "<doc>\n" .
                                    "  <p>Document Text</p>\n" .
                                    "</doc>";
                        assert_equal_xml($xml, $expected);
                });

                it('should change the text of a node', function() {
                        $xml = new FluidXml();
                        $cx = $xml->appendChild('p', true);
                        $cx->setText('Document Text');

                        $expected = "<doc>\n" .
                                    "  <p>Document Text</p>\n" .
                                    "</doc>";
                        assert_equal_xml($xml, $expected);

                        $cx->setText('Document New Text');

                        $expected = "<doc>\n" .
                                    "  <p>Document New Text</p>\n" .
                                    "</doc>";
                        assert_equal_xml($xml, $expected);
                });
        });

        describe('.appendCdata', function() {
                it('should add CDATA to the root node', function() {
                        $xml = new FluidXml();
                        $xml->appendCdata('// <, > and & are characters that should be escaped in a XML context.');

                        $expected = "<doc>" .
                                    "<![CDATA[// <, > and & are characters that should be escaped in a XML context.]]>" .
                                    "</doc>";
                        assert_equal_xml($xml, $expected);

                        $xml->appendCdata('// <second &cdata section>');

                        $expected = "<doc>" .
                                    "<![CDATA[// <, > and & are characters that should be escaped in a XML context.]]>" .
                                    "<![CDATA[// <second &cdata section>]]>" .
                                    "</doc>";
                        assert_equal_xml($xml, $expected);
                });

                it('should add CDATA to a node', function() {
                        $xml = new FluidXml();
                        $cx = $xml->appendChild('pre', true);
                        $cx->appendCdata('// <, > and & are characters that should be escaped in a XML context.');

                        $expected = "<doc>\n" .
                                    "  <pre><![CDATA[// <, > and & are characters that should be escaped in a XML context.]]></pre>\n" .
                                    "</doc>";
                        assert_equal_xml($xml, $expected);

                        $cx->appendCdata('// <second &cdata section>');

                        $expected = "<doc>\n" .
                                    "  <pre><![CDATA[// <, > and & are characters that should be escaped in a XML context.]]>" .
                                       "<![CDATA[// <second &cdata section>]]>" .
                                       "</pre>\n" .
                                    "</doc>";
                        assert_equal_xml($xml, $expected);
                });
        });

        describe('.remove', function() {
                it('should remove a descending node from the root node using xpath', function() {
                        $xml = new FluidXml();
                        $xml->appendChild('parent', true)
                            ->appendChild('child');

                        $xml->remove('/doc/parent/child');

                        $expected = "<doc>\n" .
                                    "  <parent/>\n" .
                                    "</doc>";
                        assert_equal_xml($xml, $expected);
                });

                it('should remove a descending node from the root node using a context', function() {
                        $xml = new FluidXml();
                        $cx = $xml->appendChild('parent', true)
                                  ->appendChild('child', true);

                        $xml->remove($cx);

                        $expected = "<doc>\n" .
                                    "  <parent/>\n" .
                                    "</doc>";
                        assert_equal_xml($xml, $expected);
                });

                it('should remove multiple descending nodes from the root node using xpath', function() {
                        $xml = new FluidXml();
                        $xml->appendChild('parent', true)
                            ->appendChild(['child1', 'child2'], ['class'=>'removable']);

                        $xml->remove('/doc/parent/*[@class="removable"]');

                        $expected = "<doc>\n" .
                                    "  <parent/>\n" .
                                    "</doc>";
                        assert_equal_xml($xml, $expected);
                });

                it('should remove multiple descending nodes from the root node using a context', function() {
                        $xml = new FluidXml();
                        $cx = $xml->appendChild('parent', true)
                                  ->appendChild(['child1', 'child2'], ['class'=>'removable'], true);

                        $xml->remove($cx);

                        $expected = "<doc>\n" .
                                    "  <parent/>\n" .
                                    "</doc>";
                        assert_equal_xml($xml, $expected);
                });

                it('should remove a child node from a parent node using xpath', function() {
                        $xml = new FluidXml();
                        $cx = $xml->appendChild('parent', true);
                        $cx->appendChild('child');

                        $cx->remove('child');

                        $expected = "<doc>\n" .
                                    "  <parent/>\n" .
                                    "</doc>";
                        assert_equal_xml($xml, $expected);
                });

                it('should remove a child node from a parent node using a context', function() {
                        $xml = new FluidXml();
                        $parent = $xml->appendChild('parent', true);
                        $cx     = $parent->appendChild('child', true);

                        $parent->remove($cx);

                        $expected = "<doc>\n" .
                                    "  <parent/>\n" .
                                    "</doc>";
                        assert_equal_xml($xml, $expected);
                });

                it('should remove a descending node from an ancestor node using xpath', function() {
                        $xml = new FluidXml();
                        $cx = $xml->appendChild('parent', true);
                        $cx->appendChild('child', true)
                           ->appendChild('subchild');

                        $cx->remove('child/subchild');

                        $expected = "<doc>\n" .
                                    "  <parent>\n" .
                                    "    <child/>\n" .
                                    "  </parent>\n" .
                                    "</doc>";
                        assert_equal_xml($xml, $expected);
                });

                it('should remove a descending node from an ancestor node using a context', function() {
                        $xml = new FluidXml();
                        $parent = $xml->appendChild('parent', true);
                        $cx = $parent->appendChild('child', true)
                                     ->appendChild('subchild', true);

                        $cx->remove($cx);

                        $expected = "<doc>\n" .
                                    "  <parent>\n" .
                                    "    <child/>\n" .
                                    "  </parent>\n" .
                                    "</doc>";
                        assert_equal_xml($xml, $expected);
                });

                it('should remove multiple children nodes from a parent node using xpath', function() {
                        $xml = new FluidXml();
                        $cx = $xml->appendChild('parent', true);
                        $cx->appendChild(['child1', 'child2'], ['class'=>'removable']);

                        $cx->remove('*[@class="removable"]');

                        $expected = "<doc>\n" .
                                    "  <parent/>\n" .
                                    "</doc>";
                        assert_equal_xml($xml, $expected);
                });

                it('should remove multiple children nodes from a parent node using a context', function() {
                        $xml = new FluidXml();
                        $parent = $xml->appendChild('parent', true);
                        $cx     = $parent->appendChild(['child1', 'child2'], ['class'=>'removable'], true);

                        $cx->remove($cx);

                        $expected = "<doc>\n" .
                                    "  <parent/>\n" .
                                    "</doc>";
                        assert_equal_xml($xml, $expected);
                });

                it('should remove multiple descending nodes from an ancestor node using xpath', function() {
                        $xml = new FluidXml();
                        $cx = $xml->appendChild('parent', true);
                        $cx->appendChild('child', true)
                           ->appendChild(['subchild1', 'subchild2'], ['class'=>'removable']);

                        $cx->remove('child/*[@class="removable"]');

                        $expected = "<doc>\n" .
                                    "  <parent>\n" .
                                    "    <child/>\n" .
                                    "  </parent>\n" .
                                    "</doc>";
                        assert_equal_xml($xml, $expected);
                });

                it('should remove multiple descending nodes from an ancestor node using a context', function() {
                        $xml = new FluidXml();
                        $parent = $xml->appendChild('parent', true);
                        $cx = $parent->appendChild('child', true)
                                     ->appendChild(['subchild1', 'subchild2'],
                                                   ['class'=>'removable'],
                                                   true);

                        $parent->remove($cx);

                        $expected = "<doc>\n" .
                                    "  <parent>\n" .
                                    "    <child/>\n" .
                                    "  </parent>\n" .
                                    "</doc>";
                        assert_equal_xml($xml, $expected);
                });
        });

        describe('.add', function() {
                it('should behave like .appendChild', function() {
                        $xml = new FluidXml();
                        $xml->appendChild('parent', true)
                            ->appendChild(['child1', 'child2'], ['class'=>'child']);

                        $alias = new FluidXml();
                        $alias->add('parent', true)
                              ->add(['child1', 'child2'], ['class'=>'child']);

                        $actual   = $xml->xml();
                        $expected = $alias->xml();
                        assert($actual === $expected, __($actual, $expected));
                });
        });

        describe('.prepend', function() {
                it('should behave like .prependSibling', function() {
                        $xml = new FluidXml();
                        $xml->prependSibling('sibling1', true)
                            ->prependSibling(['sibling2', 'sibling3'], ['class'=>'sibling']);

                        $alias = new FluidXml();
                        $alias->prepend('sibling1', true)
                              ->prepend(['sibling2', 'sibling3'], ['class'=>'sibling']);

                        $actual   = $xml->xml();
                        $expected = $alias->xml();
                        assert($actual === $expected, __($actual, $expected));
                });
        });

        describe('.insertSiblingBefore', function() {
                it('should behave like .prependSibling', function() {
                        $xml = new FluidXml();
                        $xml->prependSibling('sibling1', true)
                            ->prependSibling(['sibling2', 'sibling3'], ['class'=>'sibling']);

                        $alias = new FluidXml();
                        $alias->insertSiblingBefore('sibling1', true)
                              ->insertSiblingBefore(['sibling2', 'sibling3'], ['class'=>'sibling']);

                        $actual   = $xml->xml();
                        $expected = $alias->xml();
                        assert($actual === $expected, __($actual, $expected));
                });
        });

        describe('.append', function() {
                it('should behave like .appendSibling', function() {
                        $xml = new FluidXml();
                        $xml->appendSibling('sibling1', true)
                            ->appendSibling(['sibling2', 'sibling3'], ['class'=>'sibling']);

                        $alias = new FluidXml();
                        $alias->append('sibling1', true)
                              ->append(['sibling2', 'sibling3'], ['class'=>'sibling']);

                        $actual   = $xml->xml();
                        $expected = $alias->xml();
                        assert($actual === $expected, __($actual, $expected));
                });
        });

        describe('.insertSiblingAfter', function() {
                it('should behave like .appendSibling', function() {
                        $xml = new FluidXml();
                        $xml->appendSibling('sibling1', true)
                            ->appendSibling(['sibling2', 'sibling3'], ['class'=>'sibling']);

                        $alias = new FluidXml();
                        $alias->insertSiblingAfter('sibling1', true)
                              ->insertSiblingAfter(['sibling2', 'sibling3'], ['class'=>'sibling']);

                        $actual   = $xml->xml();
                        $expected = $alias->xml();
                        assert($actual === $expected, __($actual, $expected));
                });
        });

        describe('.attr', function() {
                it('should behave like .setAttribute', function() {
                        $xml = new FluidXml();
                        $xml->setAttribute('attr1', 'Attr1 Value')
                            ->setAttribute(['attr2' => 'Attr2 Value', 'attr3' => 'Attr3 Value'])
                            ->appendChild('child', true)
                            ->setAttribute('attr4', 'Attr4 Value')
                            ->setAttribute(['attr5' => 'Attr5 Value', 'attr6' => 'Attr6 Value']);

                        $alias = new FluidXml();
                        $alias->attr('attr1', 'Attr1 Value')
                              ->attr(['attr2' => 'Attr2 Value', 'attr3' => 'Attr3 Value'])
                              ->appendChild('child', true)
                              ->attr('attr4', 'Attr4 Value')
                              ->attr(['attr5' => 'Attr5 Value', 'attr6' => 'Attr6 Value']);

                        $actual   = $xml->xml();
                        $expected = $alias->xml();
                        assert($actual === $expected, __($actual, $expected));
                });
        });

        describe('.text', function() {
                it('should behave like .setText', function() {
                        $xml = new FluidXml();
                        $xml->setText('Text1')
                            ->appendChild('child', true)
                            ->setText('Text2');

                        $alias = new FluidXml();
                        $alias->text('Text1')
                              ->appendChild('child', true)
                              ->text('Text2');

                        $actual   = $xml->xml();
                        $expected = $alias->xml();
                        assert($actual === $expected, __($actual, $expected));
                });
        });
});

describe('FluidContext', function() {
        it('should be iterable returning the represented DOMNode objects', function() {
                $xml = new FluidXml();
                $cx = $xml->appendChild(['head', 'body'], true);

                $actual = $cx;
                assert_is_a($actual, \Iterator::class);

                $representation = [];
                foreach ($cx as $k => $v) {
                        $actual = \is_int($k);
                        $expected = true;
                        assert($actual === $expected, __($actual, $expected));

                        $actual = $v;
                        assert_is_a($actual, \DOMNode::class);

                        $representation[$k] = $v->nodeName;
                }

                $actual = $representation;
                $expected = [0 => 'head', 1 => 'body'];
                assert($actual === $expected, __($actual, $expected));
        });

        describe('()', function() {
                it('should accept a DOMNodeList', function() {
                        $xml = new FluidXml();
                        $cx = $xml->appendChild(['head', 'body'], true);
                        $dom = $xml->dom();

                        $domxp = new \DOMXPath($dom);
                        $nodes = $domxp->query('/doc/*');

                        $newCx = new FluidContext($dom, $nodes);

                        $actual   = $cx->asArray() === $newCx->asArray();
                        $expected = true;
                        assert($actual === $expected, __($actual, $expected));
                });

                it('should accept a FluidContext', function() {
                        $xml = new FluidXml();
                        $cx = $xml->appendChild(['head', 'body'], true);

                        $newCx = new FluidContext($xml->dom(), $cx);

                        $actual   = $cx->asArray() === $newCx->asArray();
                        $expected = true;
                        assert($actual === $expected, __($actual, $expected));
                });
        });

        describe('[]', function() {
                it('should access the nodes inside the context', function() {
                        $xml = new FluidXml();
                        $cx = $xml->appendChild(['head', 'body'], true);

                        $actual = $cx[0];
                        assert_is_a($actual, \DOMElement::class);

                        $actual = $cx[1];
                        assert_is_a($actual, \DOMElement::class);
                });

                it('should behave like an array', function() {
                        $xml = new FluidXml();
                        $cx = $xml->appendChild(['head', 'body', 'extra'], true);

                        $actual   = isset($cx[0]);
                        $expected = true;
                        assert($actual === $expected, __($actual, $expected));

                        $actual   = isset($cx[3]);
                        $expected = false;
                        assert($actual === $expected, __($actual, $expected));

                        $actual   = $cx[3];
                        $expected = null;
                        assert($actual === $expected, __($actual, $expected));

                        try {
                                $cx[] = "value";
                        } catch (\Exception $e) {
                                $actual   = $e;
                        }
                        assert_is_a($actual, \Exception::class);

                        unset($cx[1]);

                        $actual   = $cx[0]->nodeName;
                        $expected = 'head';
                        assert($actual === $expected, __($actual, $expected));

                        $actual   = $cx[1]->nodeName;
                        $expected = 'extra';
                        assert($actual === $expected, __($actual, $expected));
                });
        });

        describe('.asArray', function() {
                it('should return an array of nodes inside the context', function() {
                        $xml = new FluidXml();
                        $cx = $xml->appendChild(['head', 'body'], true);

                        $a = $cx->asArray();

                        $actual = $a;
                        assert(\is_array($actual));

                        $actual   = \count($a);
                        $expected = 2;
                        assert($actual === $expected, __($actual, $expected));
                });
        });

        describe('.length', function() {
                it('should return the number of nodes inside the context', function() {
                        $xml = new FluidXml();
                        $cx = $xml->query('/*');

                        $actual   = $cx->length();
                        $expected = 1;
                        assert($actual === $expected, __($actual, $expected));

                        $cx = $xml->appendChild(['child1', 'child2'], true);
                        $actual   = $cx->length();
                        $expected = 2;
                        assert($actual === $expected, __($actual, $expected));

                        $cx = $cx->appendChild(['subchild1', 'subchild2', 'subchild3']);
                        $actual   = $cx->length();
                        $expected = 2;
                        assert($actual === $expected, __($actual, $expected));

                        $cx = $cx->appendChild(['subchild4', 'subchild5', 'subchild6', 'subchild7'], true);
                        $actual   = $cx->length();
                        $expected = 8;
                        assert($actual === $expected, __($actual, $expected));

                        $expected = "<doc>\n"                   .
                                    "  <child1>\n"              .
                                    "    <subchild1/>\n"        .
                                    "    <subchild2/>\n"        .
                                    "    <subchild3/>\n"        .
                                    "    <subchild4/>\n"        .
                                    "    <subchild5/>\n"        .
                                    "    <subchild6/>\n"        .
                                    "    <subchild7/>\n"        .
                                    "  </child1>\n"             .
                                    "  <child2>\n"              .
                                    "    <subchild1/>\n"        .
                                    "    <subchild2/>\n"        .
                                    "    <subchild3/>\n"        .
                                    "    <subchild4/>\n"        .
                                    "    <subchild5/>\n"        .
                                    "    <subchild6/>\n"        .
                                    "    <subchild7/>\n"        .
                                    "  </child2>\n"             .
                                    "</doc>";
                        assert_equal_xml($xml, $expected);
                });
        });
});
