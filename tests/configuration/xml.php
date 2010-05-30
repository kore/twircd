<?php
/**
 * TwIRCd - Twitter IRC Server
 *
 * This file is part of TwIRCd.
 *
 * TwIRCd is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; version 3 of the License.
 *
 * TwIRCd is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with TwIRCd; if not, write to the Free Software
 * Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA
 *
 * @package Tests
 * @version $Revision$
 * @license http://www.gnu.org/licenses/gpl-3.0.txt GPL
 */

namespace TwIRCd\Tests\Configuration;

class XmlTests extends \PHPUnit_Framework_TestCase
{
    protected $tmpFile;

    public static function suite()
    {
        return new \PHPUnit_Framework_TestSuite( __CLASS__ );
    }

    /**
     * Create temp file name for storage on setup
     * 
     * @return void
     */
    public function setUp()
    {
        $this->tmpFile = tempnam( __DIR__ . '/tmp/', 'xml_' );
        unlink( $this->tmpFile );
    }

    /**
     * Remove temp file, if it has been created during the tests, again.
     * 
     * @return void
     */
    public function tearDown()
    {
        if ( is_file( $this->tmpFile ) )
        {
            unlink( $this->tmpFile );
        }
    }

    public function testGetDefaultLastUpdate()
    {
        $conf = new \TwIRCd\Configuration\Xml( $this->tmpFile );
        $this->assertSame( null, $conf->getLastUpdate( 'foo' ) );
    }

    public function testSetLastUpdate()
    {
        $conf = new \TwIRCd\Configuration\Xml( $this->tmpFile );
        $conf->setLastUpdate( 'foo', 12345 );
        $this->assertSame( '12345', $conf->getLastUpdate( 'foo' ) );
    }

    public function testSetMultipleLastUpdate()
    {
        $conf = new \TwIRCd\Configuration\Xml( $this->tmpFile );
        $conf->setLastUpdate( 'foo', 12345 );
        $conf->setLastUpdate( 'bar', 67890 );
        $this->assertSame( '12345', $conf->getLastUpdate( 'foo' ) );
        $this->assertSame( '67890', $conf->getLastUpdate( 'bar' ) );
    }

    public function testGetNoSearches()
    {
        $conf = new \TwIRCd\Configuration\Xml( $this->tmpFile );
        $this->assertSame( array(), $conf->getSearches() );
    }

    public function testSetSearch()
    {
        $conf = new \TwIRCd\Configuration\Xml( $this->tmpFile );
        $conf->setSearch( 'foo', 'foo' );
        $this->assertSame(
            array(
                'foo' => 'foo',
            ),
            $conf->getSearches()
        );
    }

    public function testSetMultipleSearches()
    {
        $conf = new \TwIRCd\Configuration\Xml( $this->tmpFile );
        $conf->setSearch( 'foo', 'foo' );
        $conf->setSearch( 'bar', 'foo' );
        $this->assertSame(
            array(
                'foo' => 'foo',
                'bar' => 'foo',
            ),
            $conf->getSearches()
        );
    }

    public function testUpdateSearch()
    {
        $conf = new \TwIRCd\Configuration\Xml( $this->tmpFile );
        $conf->setSearch( 'foo', 'foo' );
        $conf->setSearch( 'foo', 'bar' );
        $this->assertSame(
            array(
                'foo' => 'bar',
            ),
            $conf->getSearches()
        );
    }

    public function testRemoveSearch()
    {
        $conf = new \TwIRCd\Configuration\Xml( $this->tmpFile );
        $conf->setSearch( 'foo', 'foo' );
        $conf->setSearch( 'bar', 'foo' );
        $conf->removeSearch( 'foo' );
        $this->assertSame(
            array(
                'bar' => 'foo',
            ),
            $conf->getSearches()
        );
    }

    public function testRemoveAllSearches()
    {
        $conf = new \TwIRCd\Configuration\Xml( $this->tmpFile );
        $conf->setSearch( 'foo', 'foo' );
        $conf->setSearch( 'bar', 'foo' );
        $conf->removeSearch( 'foo' );
        $conf->removeSearch( 'bar' );
        $this->assertSame(
            array(
            ),
            $conf->getSearches()
        );
    }

    public function testRemoveUnknownSearch()
    {
        $conf = new \TwIRCd\Configuration\Xml( $this->tmpFile );
        $conf->removeSearch( 'unknown' );
        $this->assertSame(
            array(
            ),
            $conf->getSearches()
        );
    }

    public function testGetNoGroups()
    {
        $conf = new \TwIRCd\Configuration\Xml( $this->tmpFile );
        $this->assertSame( array(), $conf->getGroups() );
    }

    public function testSetGroup()
    {
        $conf = new \TwIRCd\Configuration\Xml( $this->tmpFile );
        $conf->setGroup( 'foo', array( 'foo' ) );
        $this->assertSame(
            array(
                'foo' => array( 'foo' ),
            ),
            $conf->getGroups()
        );
    }

    public function testSetMultipleGroups()
    {
        $conf = new \TwIRCd\Configuration\Xml( $this->tmpFile );
        $conf->setGroup( 'foo', array( 'foo', 'bar' ) );
        $conf->setGroup( 'bar', array( 'foo' ) );
        $this->assertSame(
            array(
                'foo' => array( 'foo', 'bar' ),
                'bar' => array( 'foo' ),
            ),
            $conf->getGroups()
        );
    }

    public function testUpdateGroup()
    {
        $conf = new \TwIRCd\Configuration\Xml( $this->tmpFile );
        $conf->setGroup( 'foo', array( 'foo', 'bar' ) );
        $conf->setGroup( 'foo', array( 'foo' ) );
        $this->assertSame(
            array(
                'foo' => array( 'foo' ),
            ),
            $conf->getGroups()
        );
    }

    public function testRemoveGroup()
    {
        $conf = new \TwIRCd\Configuration\Xml( $this->tmpFile );
        $conf->setGroup( 'foo', array( 'foo', 'bar' ) );
        $conf->setGroup( 'bar', array( 'foo' ) );
        $conf->removeGroup( 'foo' );
        $this->assertSame(
            array(
                'bar' => array( 'foo' ),
            ),
            $conf->getGroups()
        );
    }

    public function testRemoveAllGroups()
    {
        $conf = new \TwIRCd\Configuration\Xml( $this->tmpFile );
        $conf->setGroup( 'foo', array( 'foo', 'bar' ) );
        $conf->setGroup( 'bar', array( 'foo' ) );
        $conf->removeGroup( 'foo' );
        $conf->removeGroup( 'bar' );
        $this->assertSame(
            array(
            ),
            $conf->getGroups()
        );
    }

    public function testRemoveUnknownGroup()
    {
        $conf = new \TwIRCd\Configuration\Xml( $this->tmpFile );
        $conf->removeGroup( 'unknown' );
        $this->assertSame(
            array(
            ),
            $conf->getGroups()
        );
    }

    public function testUpdateAmpersandGroup()
    {
        $conf = new \TwIRCd\Configuration\Xml( $this->tmpFile );
        $conf->setGroup( '&foo', array( 'foo', 'bar' ) );
        $conf->setGroup( '&foo', array( 'foo' ) );
        $this->assertSame(
            array(
                '&foo' => array( 'foo' ),
            ),
            $conf->getGroups()
        );
    }

    public function testRemoveAmpersandGroup()
    {
        $conf = new \TwIRCd\Configuration\Xml( $this->tmpFile );
        $conf->setGroup( '&foo', array( 'foo', 'bar' ) );
        $conf->setGroup( '&bar', array( 'foo' ) );
        $conf->removeGroup( '&foo' );
        $this->assertSame(
            array(
                '&bar' => array( 'foo' ),
            ),
            $conf->getGroups()
        );
    }

    public function testGetNonExsistentValueNoCustomDefault()
    {
        $conf = new \TwIRCd\Configuration\Xml( $this->tmpFile );

        $this->assertEquals(
            '',
            $conf->getValue( 'foo' )
        );
    }

    public function testGetNonExsistentValueCustomDefault()
    {
        $conf = new \TwIRCd\Configuration\Xml( $this->tmpFile );

        $this->assertEquals(
            23,
            $conf->getValue( 'foo', 23 )
        );
    }

    public function testSetNewValue()
    {
        $conf = new \TwIRCd\Configuration\Xml( $this->tmpFile );
        $conf->setValue( 'foo', 'bar' );

        $this->assertEquals(
            'bar',
            $conf->getValue( 'foo' )
        );
    }

    public function testSetExistingValue()
    {
        $conf = new \TwIRCd\Configuration\Xml( $this->tmpFile );
        $conf->setValue( 'foo', 'bar' );
        $conf->setValue( 'foo', 'baz' );

        $this->assertEquals(
            'baz',
            $conf->getValue( 'foo' )
        );
    }

    public function testGetExsistentValueNoCustomDefault()
    {
        $conf = new \TwIRCd\Configuration\Xml( $this->tmpFile );
        $conf->setValue( 'foo', 'bar' );

        $this->assertEquals(
            'bar',
            $conf->getValue( 'foo' )
        );
    }

    public function testGetExsistentValueCustomDefault()
    {
        $conf = new \TwIRCd\Configuration\Xml( $this->tmpFile );
        $conf->setValue( 'foo', 'bar' );

        $this->assertEquals(
            'bar',
            $conf->getValue( 'foo', 23 )
        );
    }
}
