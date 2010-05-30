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
 * @package Core
 * @version $Revision$
 * @license http://www.gnu.org/licenses/gpl-3.0.txt GPL
 */

namespace TwIRCd\Configuration;

/**
 * XML configuration backend
 * 
 * @package Core
 * @version $Revision$
 * @license http://www.gnu.org/licenses/gpl-3.0.txt GPL
 */
class Xml extends \TwIRCd\Configuration
{
    /**
     * File, which stores the configuration
     * 
     * @var string
     */
    protected $file;

    /**
     * DOMDocument containing the configuration
     * 
     * @var \DOMDocument
     */
    protected $document;

    /**
     * Construct XML configuration from config file
     * 
     * @param mixed $file 
     * @return void
     */
    public function __construct( $file )
    {
        $this->file     = $file;
        $this->document = new \DOMDocument();
        $this->document->formatOutput       = true;
        $this->document->preserveWhiteSpace = false;

        if ( is_file( $file ) )
        {
            $this->document->load( $file );
        }
        else
        {
            $this->document->appendChild( $this->document->createElement( 'config' ) );
        }
    }

    /**
     * Persist configuration to disk
     * 
     * @return void
     */
    protected function store()
    {
        $this->document->save( $this->file );
    }

    /**
     * Get last update time
     *
     * Get timestamp of last performed update
     * 
     * @param string $type 
     * @return string
     */
    public function getLastUpdate( $type )
    {
        $xpath = new \DOMXPath( $this->document );
        $updateTime = $xpath->query( '/config/updates/update[@type = "' . $type . '"]' );
        if ( !$updateTime->length )
        {
            return null;
        }

        return $updateTime->item( 0 )->textContent;
    }

    /**
     * Set last update time
     *
     * Set timestamp of last performed update
     * 
     * @param string $type 
     * @param string $value 
     * @return void
     */
    public function setLastUpdate( $type, $value )
    {
        $xpath = new \DOMXPath( $this->document );
        $updateTime = $xpath->query( '/config/updates/update[@type = "' . $type . '"]' );
        if ( !$updateTime->length )
        {
            $container = $xpath->query( '/config/updates' );
            if ( !$container->length )
            {
                $container = $this->document->documentElement->appendChild(
                    $this->document->createElement( 'updates' )
                );
            }
            else
            {
                $container = $container->item( 0 );
            }

            $container->appendChild(
                $update = $this->document->createElement( 'update', $value )
            );
            $update->setAttribute( 'type', $type );
            return $this->store();
        }

        $updateTime->item( 0 )->nodeValue = $value;
        return $this->store();
    }

    /**
     * Set search term
     *
     * Set the search term for an existing search, or create a new search entry 
     * with the defined name and search term.
     * 
     * @param string $channel 
     * @param string $search 
     * @return void
     */
    public function setSearch( $channel, $search )
    {
        $xpath = new \DOMXPath( $this->document );
        $nodes = $xpath->query( '/config/searches/search[@channel = "' . $channel . '"]' );
        if ( !$nodes->length )
        {
            $container = $xpath->query( '/config/searches' );
            if ( !$container->length )
            {
                $container = $this->document->documentElement->appendChild(
                    $this->document->createElement( 'searches' )
                );
            }
            else
            {
                $container = $container->item( 0 );
            }

            $container->appendChild(
                $node = $this->document->createElement( 'search', $search )
            );
            $node->setAttribute( 'channel', $channel );
            return $this->store();
        }

        $nodes->item( 0 )->nodeValue = $search;
        return $this->store();
    }

    /**
     * Remove search
     *
     * Remove the given search from the lsit of defined searches.
     * 
     * @param string $channel 
     * @return void
     */
    public function removeSearch( $channel )
    {
        $xpath = new \DOMXPath( $this->document );
        $nodes = $xpath->query( '/config/searches/search[@channel = "' . $channel . '"]' );
        if ( $nodes->length )
        {
            $node = $nodes->item( 0 );
            $node->parentNode->removeChild( $node );
            return $this->store();
        }
    }

    /**
     * Get all searches
     *
     * Get an array with all searches, where the key is the channel the search 
     * has been defined for, and the value is the search term.
     * 
     * @return array
     */
    public function getSearches()
    {
        $searches = array();
        $xpath = new \DOMXPath( $this->document );
        $nodes = $xpath->query( '/config/searches/search' );
        foreach ( $nodes as $node )
        {
            $searches[$node->getAttribute( 'channel' )] = $node->nodeValue;
        }

        return $searches;
    }

    /**
     * Set group term
     *
     * Set the group term for an existing group, or create a new group entry 
     * with the defined name and group term.
     * 
     * @param string $group 
     * @param array $users
     * @return void
     */
    public function setGroup( $group, array $users = array() )
    {
        $xpath = new \DOMXPath( $this->document );
        $nodes = $xpath->query( '/config/groups/group[@name = "' . $group . '"]' );
        if ( !$nodes->length )
        {
            $container = $xpath->query( '/config/groups' );
            if ( !$container->length )
            {
                $container = $this->document->documentElement->appendChild(
                    $this->document->createElement( 'groups' )
                );
            }
            else
            {
                $container = $container->item( 0 );
            }

            $container->appendChild(
                $node = $this->document->createElement( 'group', htmlspecialchars( implode( ',', array_filter( $users, function( $user ) { return !empty( $user ); } ) ) ) )
            );
            $node->setAttribute( 'name', $group );
            return $this->store();
        }

        $nodes->item( 0 )->nodeValue = implode( ',', $users );
        return $this->store();
    }

    /**
     * Remove group
     *
     * Remove the given group from the lsit of defined groups.
     * 
     * @param string $group 
     * @return void
     */
    public function removeGroup( $group )
    {
        $xpath = new \DOMXPath( $this->document );
        $nodes = $xpath->query( '/config/groups/group[@name = "' . $group . '"]' );
        if ( $nodes->length )
        {
            $node = $nodes->item( 0 );
            $node->parentNode->removeChild( $node );
            return $this->store();
        }
    }

    /**
     * Get all groups
     *
     * Get an array with all groups, where the key is the channel the group 
     * has been defined for, and the value is the group term.
     * 
     * @return array
     */
    public function getGroups()
    {
        $groups = array();
        $xpath = new \DOMXPath( $this->document );
        $nodes = $xpath->query( '/config/groups/group' );
        foreach ( $nodes as $node )
        {
            $groups[$node->getAttribute( 'name' )] = explode( ',', $node->nodeValue );
        }

        return $groups;
    }

    /**
     * Retrieves a value from the simple key-value store.
     * 
     * Returns $default, if the desired value is not set.
     *
     * @param string $key
     * @param string $default
     */ 
    public function getValue( $key, $default = null )
    {
        $xpath = new \DOMXPath( $this->document );
        $nodes = $xpath->query( '/config/values/value[@key = "' . $key . '"]' );
        if ( $nodes->length != 0 )
        {
            return $nodes->item( 0 )->nodeValue;
        }
        return $default;
    }

    /**
     * Sets a value in the simple key-value store.
     *
     * @param string $key
     * @param string $value
     */
    public function setValue( $key, $value )
    {
        $xpath = new \DOMXPath( $this->document );
        $nodes = $xpath->query( '/config/values/value[@key = "' . $key . '"]' );

        $node = null;
        if ( $nodes->length == 0 )
        {
            $node = $this->document->createElement( 'value' );
            $node->setAttribute( 'key', $key );

            $parents = $xpath->query( '/config/values' );
            $parent = null;
            if ( $parents->length == 0 )
            {
                $parent = $this->document->createElement( 'values' );
                $this->document->documentElement->appendChild( $parent );
            }
            else
            {
                $parent = $parents->item( 0 );
            }

            $parent->appendChild( $node );
        }
        else
        {
            $node = $nodes->item( 0 );
        }
        $node->nodeValue = $value;
    }
}

