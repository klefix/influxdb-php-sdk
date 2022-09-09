<?php
namespace InfluxDB\Adapter\Udp;

use DateTime;
use DateTimeZone;
use InfluxDB\Adapter\Udp\Options;
use GuzzleHttp\Client as GuzzleHttpClient;
use InfluxDB\Adapter\GuzzleAdapter as InfluxHttpAdapter;
use InfluxDB\Client;
use Prophecy\Argument;

class WriterTest extends \PHPUnit\Framework\TestCase
{
    /**
     * @dataProvider getMessages
     */
    public function testRewriteMessages($input, $response)
    {
        $object = $this->getMockBuilder("InfluxDB\Adapter\Udp\Writer")
            ->setConstructorArgs([new Options()])
            ->setMethods(["write"])
            ->getMock();
        $object->expects($this->once())
            ->method("write")
            ->with($response);

        $object->send($input);
    }

    public function getMessages()
    {
        return [
            [
                [
                    "time" => "2009-11-10T23:00:00Z",
                    "points" => [
                        [
                            "measurement" => "cpu",
                            "fields" => [
                                "value" => 1.,
                            ],
                        ],
                    ],
                ],
                "cpu value=1 1257894000000000000"
            ],
            [
                [
                    "time" => "2009-11-10T23:00:00Z",
                    "points" => [
                        [
                            "measurement" => "cpu",
                            "fields" => [
                                "value" => 1.,
                                "string" => "escape",
                            ],
                        ],
                    ],
                ],
                "cpu value=1,string=\"escape\" 1257894000000000000"
            ],
            [
                [
                    "tags" => [
                        "region" => "us-west",
                        "host" => "serverA",
                        "env" => "prod",
                        "target" => "servers",
                        "zone" => "1c",
                    ],
                    "time" => "2009-11-10T23:00:00Z",
                    "points" => [
                        [
                            "measurement" => "cpu",
                            "fields" => [
                                "cpu" => 18.12,
                                "free" => 712432.,
                            ],
                        ],
                    ],
                ],
                "cpu,region=us-west,host=serverA,env=prod,target=servers,zone=1c cpu=18.12,free=712432 1257894000000000000"
            ],
            [
                [
                    "tags" => [
                        "region" => "us-west",
                        "host" => "serverA",
                        "env" => "prod",
                        "target" => "servers",
                        "zone" => "1c",
                    ],
                    "time" => "2009-11-10T23:00:00Z",
                    "points" => [
                        [
                            "measurement" => "cpu",
                            "fields" => [
                                "cpu" => 18.12,
                            ],
                        ],
                        [
                            "measurement" => "mem",
                            "fields" => [
                                "free" => 712432.,
                            ],
                        ],
                    ],
                ],
                <<<EOF
cpu,region=us-west,host=serverA,env=prod,target=servers,zone=1c cpu=18.12 1257894000000000000
mem,region=us-west,host=serverA,env=prod,target=servers,zone=1c free=712432 1257894000000000000
EOF
            ],
        ];
    }

    /**
     * @group udp
     */
    public function testUdpIpWriteDataWillBeConvertedAsLineProtocol()
    {
        $options = new Options();
        $adapter = $this->getMockBuilder("InfluxDB\\Adapter\\Udp\\Writer")
            ->setConstructorArgs([$options])
            ->setMethods(["write", "generateTimeInNanoSeconds"])
            ->getMock();

        $adapter->expects($this->any())
            ->method("generateTimeInNanoSeconds")
            ->will($this->returnValue(1245));

        $adapter->expects($this->once())
            ->method("write")
            ->with($this->matchesRegularExpression("/udp.test mark=\"element\" \d+/i"));

        $adapter->send([
            "points" => [
                [
                    "measurement" => "udp.test",
                    "fields" => [
                        "mark" => "element"
                    ]
                ]
            ]
        ]);
    }

    /**
     * @group udp
     */
    public function testSendMultipleMeasurementWithUdpIp()
    {
        $options = new Options();
        $adapter = $this->getMockBuilder("InfluxDB\\Adapter\\Udp\\Writer")
            ->setConstructorArgs([$options])
            ->setMethods(["write", "generateTimeInNanoSeconds"])
            ->getMock();

        $adapter->expects($this->any())
            ->method("generateTimeInNanoSeconds")
            ->will($this->onConsecutiveCalls(1245, 1246));

        $adapter->expects($this->once())
            ->method("write")
            ->with($this->matchesRegularExpression(<<<EOF
/mem free=712423 \d+
cpu cpu=18.12 \d+/i
EOF
        ));

        $adapter->send([
            "points" => [
                [
                    "measurement" => "mem",
                    "fields" => [
                        "free" => 712423.,
                    ],
                ],
                [
                    "measurement" => "cpu",
                    "fields" => [
                        "cpu" => 18.12,
                    ],
                ],
            ]
        ]);
    }

    /**
     * @group udp
     */
    public function testFillWithGlobalTags()
    {
        $options = (new Options())->setTags(["dc" => "eu-west"]);
        $adapter = $this->getMockBuilder("InfluxDB\\Adapter\\Udp\\Writer")
            ->setConstructorArgs([$options])
            ->setMethods(["write"])
            ->getMock();

        $adapter->expects($this->once())
            ->method("write")
            ->with($this->matchesRegularExpression("/mem,dc=eu-west free=712423 \d+/i"));

        $adapter->send([
            "points" => [
                [
                    "measurement" => "mem",
                    "fields" => [
                        "free" => 712423.,
                    ],
                ],
            ]
        ]);
    }

    /**
     * @group udp
     */
    public function testMergeGlobalTags()
    {
        $options = (new Options())
            ->setTags(["dc" => "eu-west"]);
        $adapter = $this->getMockBuilder("InfluxDB\\Adapter\\Udp\\Writer")
            ->setConstructorArgs([$options])
            ->setMethods(["write", "generateTimeInNanoSeconds"])
            ->getMock();

        $adapter->expects($this->any())
            ->method("generateTimeInNanoSeconds")
            ->will($this->returnValue(1245));

        $adapter->expects($this->once())
            ->method("write")
            ->with($this->matchesRegularExpression(<<<EOF
/mem,dc=eu-west,region=eu-west-1 free=712423 \d+/i
EOF
        ));

        $adapter->send([
            "tags" => [
                "region" => "eu-west-1",
            ],
            "points" => [
                [
                    "measurement" => "mem",
                    "fields" => [
                        "free" => 712423.,
                    ],
                ],
            ]
        ]);
    }

    /**
     * @group udp
     */
    public function testMergeFullTagsPositions()
    {
        $options = (new Options())
            ->setTags(["dc" => "eu-west"]);
        $adapter = $this->getMockBuilder("InfluxDB\\Adapter\\Udp\\Writer")
            ->setConstructorArgs([$options])
            ->setMethods(["write", "generateTimeInNanoSeconds"])
            ->getMock();

        $adapter->expects($this->any())
            ->method("generateTimeInNanoSeconds")
            ->will($this->returnValue(1245));

        $adapter->expects($this->once())
            ->method("write")
            ->with($this->matchesRegularExpression(<<<EOF
/mem,dc=eu-west,region=eu-west-1,location=ireland free=712423 \d+/i
EOF
        ));

        $adapter->send([
            "tags" => [
                "region" => "eu-west-1",
            ],
            "points" => [
                [
                    "measurement" => "mem",
                    "tags" => [
                        "location" => "ireland",
                    ],
                    "fields" => [
                        "free" => 712423.,
                    ],
                ],
            ]
        ]);
    }

    /**
     * @dataProvider getMessagesWithIntegers
     */
    public function testWithIntegers($input, $response)
    {
        $options = new Options();

        $object = $this->getMockBuilder("InfluxDB\Adapter\Udp\Writer")
            ->setConstructorArgs([$options])
            ->setMethods(["write"])
            ->getMock();
        $object->expects($this->once())
            ->method("write")
            ->with($response);

        $object->send($input);
    }

    public function getMessagesWithIntegers()
    {
        return [
            [
                [
                    "time" => "2009-11-10T23:00:00Z",
                    "points" => [
                        [
                            "measurement" => "cpu",
                            "fields" => [
                                "value" => 1,
                            ],
                        ],
                    ],
                ],
                "cpu value=1i 1257894000000000000"
            ],
            [
                [
                    "time" => "2009-11-10T23:00:00Z",
                    "points" => [
                        [
                            "measurement" => "cpu",
                            "fields" => [
                                "value" => 1,
                                "string" => "escape",
                            ],
                        ],
                    ],
                ],
                "cpu value=1i,string=\"escape\" 1257894000000000000"
            ],
            [
                [
                    "tags" => [
                        "region" => "us-west",
                        "host" => "serverA",
                        "env" => "prod",
                        "target" => "servers",
                        "zone" => "1c",
                    ],
                    "time" => "2009-11-10T23:00:00Z",
                    "points" => [
                        [
                            "measurement" => "cpu",
                            "fields" => [
                                "cpu" => 18.12,
                                "free" => 712432,
                            ],
                        ],
                    ],
                ],
                "cpu,region=us-west,host=serverA,env=prod,target=servers,zone=1c cpu=18.12,free=712432i 1257894000000000000"
            ],
            [
                [
                    "tags" => [
                        "region" => "us-west",
                        "host" => "serverA",
                        "env" => "prod",
                        "target" => "servers",
                        "zone" => "1c",
                    ],
                    "time" => "2009-11-10T23:00:00Z",
                    "points" => [
                        [
                            "measurement" => "cpu",
                            "fields" => [
                                "cpu" => 18.12,
                            ],
                        ],
                        [
                            "measurement" => "mem",
                            "fields" => [
                                "free" => 712432,
                            ],
                        ],
                    ],
                ],
                <<<EOF
cpu,region=us-west,host=serverA,env=prod,target=servers,zone=1c cpu=18.12 1257894000000000000
mem,region=us-west,host=serverA,env=prod,target=servers,zone=1c free=712432i 1257894000000000000
EOF
            ],
        ];
    }
}
