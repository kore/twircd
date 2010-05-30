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

namespace TwIRCd;

/**
 * TwIRCd configuration base class
 * 
 * @package Core
 * @version $Revision$
 * @license http://www.gnu.org/licenses/gpl-3.0.txt GPL
 */
abstract class Configuration
{
    /**
     * Get last update time
     *
     * Get timestamp of last performed update
     * 
     * @param string $type 
     * @return string
     */
    abstract public function getLastUpdate( $type );

    /**
     * Set last update time
     *
     * Set timestamp of last performed update
     * 
     * @param string $type 
     * @param string $value 
     * @return void
     */
    abstract public function setLastUpdate( $type, $value );

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
    abstract public function setSearch( $channel, $search );

    /**
     * Remove search
     *
     * Remove the given search from the lsit of defined searches.
     * 
     * @param string $channel 
     * @return void
     */
    abstract public function removeSearch( $channel );

    /**
     * Get all searches
     *
     * Get an array with all searches, where the key is the channel the search 
     * has been defined for, and the value is the search term.
     * 
     * @return array
     */
    abstract public function getSearches();

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
    abstract public function setGroup( $group, array $users = array() );

    /**
     * Remove group
     *
     * Remove the given group from the lsit of defined groups.
     * 
     * @param string $group 
     * @return void
     */
    abstract public function removeGroup( $group );

    /**
     * Get all groups
     *
     * Get an array with all groups, where the key is the channel the group 
     * has been defined for, and the value is the group term.
     * 
     * @return array
     */
    abstract public function getGroups();

    /**
     * Sets a value in the simple key-value store.
     *
     * @param string $key
     * @param string $value
     */
    abstract public function setValue( $key, $value );

    /**
     * Retrieves a value from the simple key-value store.
     * 
     * Returns $default, if the desired value is not set.
     *
     * @param string $key
     * @param string $default
     */ 
    abstract public function getValue( $key, $default = null );
}

