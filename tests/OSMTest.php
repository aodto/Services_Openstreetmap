<?php
/**
 * OSMTest.php
 * 25-Apr-2011
 *
 * PHP Version 5
 *
 * @category Services
 * @package  Services_Openstreemap
 * @author   Ken Guest <kguest@php.net>
 * @license  BSD http://www.opensource.org/licenses/bsd-license.php
 * @version  CVS: <cvs_id>
 * @link     FooTest.php
 * @todo
 */

require_once 'Services/Openstreetmap.php';

require_once 'HTTP/Request2.php';
require_once 'HTTP/Request2/Adapter/Mock.php';
require_once 'PHPUnit/Framework/TestCase.php';


class OSMTest extends PHPUnit_Framework_TestCase
{
    public function testConfig()
    {
        $osm = new Services_Openstreetmap();
        $this->assertEquals(
            $osm->getConfig(),
            array (
                'server' => 'http://www.openstreetmap.org/',
                'api_version' => '0.6',
                'User-Agent' => 'Services_Openstreetmap',
                'adapter' => 'HTTP_Request2_Adapter_Socket',
            )
        );
        $this->assertEquals('0.6', $osm->getConfig('api_version'));
        $osm->setConfig('User-Agent', 'Acme 1.2');
        $this->assertEquals($osm->getConfig('User-Agent'), 'Acme 1.2');
        $osm->setConfig('api_version', '0.5');
        $this->assertEquals($osm->getConfig('api_version'), '0.5');
    }

    /**
     * Test unknown config detection
     *
     * @expectedException Services_Openstreetmap_Exception
     * @expectedExceptionMessage Unknown config parameter 'api'
     *
     * @return void
     */
    public function testConfig2()
    {
        $osm = new Services_Openstreetmap();
        $osm->setConfig('api', '0.5');
    }

    /**
     * Test unknown config detection
     *
     * @expectedException Services_Openstreetmap_Exception
     * @expectedExceptionMessage Unknown config parameter 'api'
     *
     * @return void
     */
    public function testConfig3()
    {
        $osm = new Services_Openstreetmap();
        $osm->getConfig('api');
    }

    public function testCapabilities()
    {
        $mock = new HTTP_Request2_Adapter_Mock();
        $mock->addResponse(fopen('./responses/capabilities.xml', 'rb'));

        $config = array('adapter' => $mock);
        $osm = new Services_Openstreetmap($config);
        $this->assertEquals($osm->getTimeout(), 300);
    }

    public function testCapabilities2()
    {
        $mock = new HTTP_Request2_Adapter_Mock();
        $mock->addResponse(fopen('./responses/capabilities2.xml', 'rb'));

        $config = array('adapter' => $mock);
        $osm = new Services_Openstreetmap($config);
        $this->assertEquals($osm->getMinVersion(), "0.5");
        $this->assertEquals($osm->getMaxVersion(), "0.6");
    }

    public function testGetChangeset()
    {
        $mock = new HTTP_Request2_Adapter_Mock();
        $mock->addResponse(fopen('./responses/capabilities.xml', 'rb'));
        $mock->addResponse(fopen('./responses/changeset.xml', 'rb'));

        $cId = 2217466;

        $config = array('adapter' => $mock);
        $osm = new Services_Openstreetmap($config);
        $changeset = $osm->getChangeSet($cId);
        $this->assertEquals($cId, (int) $changeset->id());
    }

    public function testGetNode()
    {
        $id = 52245107;

        $mock = new HTTP_Request2_Adapter_Mock();
        $mock->addResponse(fopen('./responses/capabilities.xml', 'rb'));
        $mock->addResponse(fopen('./responses/node.xml', 'rb'));

        $config = array('adapter' => $mock);
        $osm = new Services_Openstreetmap($config);
        $node = $osm->getNode($id);
        $tags = $node->tags();

        $this->assertEquals($id, $node->id());
        $this->assertEquals($tags['name'], 'Nenagh Bridge');
        $this->assertEquals("52.881667", $node->lat());
        $this->assertEquals("-8.195833", $node->lon());
    }

    public function testGetWay()
    {
        $id = 25978036;

        $mock = new HTTP_Request2_Adapter_Mock();
        $mock->addResponse(fopen('./responses/capabilities.xml', 'rb'));
        $mock->addResponse(fopen('./responses/way.xml', 'rb'));

        $config = array('adapter' => $mock);
        $osm = new Services_Openstreetmap($config);
        $way = $osm->getWay($id);
        $tags = $way->tags();
        $this->assertEquals($id, (int) $way->attributes()->id);
        $this->assertEquals($tags['highway'], 'service');
        $this->assertEquals($way->nodes(), array("283393706","283393707"));
    }

    public function testGetHistory()
    {
        $id = 52245107;

        $mock = new HTTP_Request2_Adapter_Mock();
        $mock->addResponse(fopen('./responses/capabilities.xml', 'rb'));
        $mock->addResponse(fopen('./responses/node_history.xml', 'rb'));

        $config = array('adapter' => $mock);
        $osm = new Services_Openstreetmap($config);
        $history = $osm->getHistory('node', $id);
        $xml = simplexml_load_string($history);
        $n = $xml->xpath('//osm');
        $this->assertEquals($id, (int) ($n[0]->node->attributes()->id));
    }

    /**
     * Test that the getHistory method detects that it's been passed
     * an unsupported element type.
     *
     * @expectedException Services_Openstreetmap_Exception
     * @expectedExceptionMessage Invalid Element Type
     *
     * @return void
     */
    public function testGetHistoryUnsupportedElement()
    {
        $id = 25978036;

        $mock = new HTTP_Request2_Adapter_Mock();
        $mock->addResponse(fopen('./responses/capabilities.xml', 'rb'));

        $config = array('adapter' => $mock);
        $osm = new Services_Openstreetmap($config);
        $history = $osm->getHistory('note', $id);
    }

    public function testGetRelation()
    {
        $id = 1152802;

        $mock = new HTTP_Request2_Adapter_Mock();
        $mock->addResponse(fopen('./responses/capabilities.xml', 'rb'));
        $mock->addResponse(fopen('./responses/relation.xml', 'rb'));
        $mock->addResponse(fopen('./responses/relation_changeset.xml', 'rb'));

        $config = array('adapter' => $mock);
        $osm = new Services_Openstreetmap($config);
        $relation = $osm->getRelation($id);
        $this->assertEquals($id, $relation->id());
        $changeset_id = (int) $relation->attributes()->changeset;
        $tags = $relation->tags();
        $this->assertEquals($tags['name'], 'Mitchell Street');
        $this->assertEquals($tags['type'], 'associatedStreet');


        $changeset = $osm->getChangeset($changeset_id);
        $this->assertEquals($changeset_id, $changeset->id());
        $tags = $changeset->tags();
        $this->assertEquals($tags['comment'], 'IE. Nenagh. Mitchell Street POIs');
    }

    public function testBboxToMinMax()
    {
        $osm = new Services_Openstreetmap();
        $this->assertEquals(
            $osm->bboxToMinMax(
                "0.0327873", "52.260074599999996",
                "0.0767326", "52.282047299999995"
            ),
            array(
                "52.260074599999996", "0.0327873",
                "52.282047299999995", "0.0767326",
            )
        );

    }
}
// vim:set et ts=4 sw=4:
?>