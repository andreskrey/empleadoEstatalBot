<?php

namespace empleadoEstatalBot\Test;

use empleadoEstatalBot;

class RedditManagerTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @param string $originalString String to be sluggified
     * @param string $expectedResult What we expect our slug result to be
     *
     * @dataProvider providerTestMarkdownGeneratorGeneratesMarkdown
     */
    public function testMarkdownGeneratorGeneratesMarkdown($originalString, $expectedResult)
    {
        $reddit = new empleadoEstatalBot\RedditManager();
        $result = $this->invokeMethod($reddit, 'buildMarkdown', array($originalString));

        $this->assertEquals($expectedResult, $result);
    }

    public function providerTestMarkdownGeneratorGeneratesMarkdown()
    {
        return array(
            array('<H1>This is a title</H1>', '# This is a title'),
            array('<H2>This is a subtitle</H2>', '## This is a subtitle'),
        );
    }

    /**
     * Call protected/private method of a class.
     *
     * @param object &$object Instantiated object that we will run method on.
     * @param string $methodName Method name to call
     * @param array $parameters Array of parameters to pass into method.
     *
     * @return mixed Method return.
     */
    public function invokeMethod(&$object, $methodName, array $parameters = array())
    {
        $reflection = new \ReflectionClass(get_class($object));
        $method = $reflection->getMethod($methodName);
        $method->setAccessible(true);

        return $method->invokeArgs($object, $parameters);
    }
}