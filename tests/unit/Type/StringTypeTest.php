<?php
namespace InfluxDB\Type;

class StringTypeTest extends \PHPUnit\Framework\TestCase
{
    /**
     * @dataProvider stringProvider
     */
    public function testConversions($in, $out)
    {
        $this->assertEquals($out, new StringType($in));
    }

    public function stringProvider()
    {
        return [
            [true, '"1"'],
            ["walter", '"walter"'],
            ["12", '"12"'],
            ["12.153", '"12.153"'],
            [12.153, '"12.153"'],
            [12, '"12"'],
        ];
    }
}


