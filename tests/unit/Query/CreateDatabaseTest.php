<?php
namespace InfluxDB\Query;

class CreateDatabaseTest extends \PHPUnit\Framework\TestCase
{
    /**
     * @dataProvider queries
     */
    public function testCreateDatabaseQuery($name, $query)
    {
        $db = new CreateDatabase();
        $res = $db($name);

        $this->assertEquals($query, $res);
    }

    public function queries()
    {
        return [
            ["mydb", 'CREATE DATABASE "mydb"'],
            ['my"db"', 'CREATE DATABASE "my\"db\""'],
            ["my'db'", "CREATE DATABASE \"my\'db\'\""],
        ];
    }
}
