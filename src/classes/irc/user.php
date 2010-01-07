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

namespace TwIRCd\Irc;


/**
 * IRC User object, persisting the state and connection information for a 
 * single user.
 * 
 * @package Core
 * @version $Revision$
 * @license http://www.gnu.org/licenses/gpl-3.0.txt GPL
 */
class User
{
    /**
     * User connection stream
     * 
     * @var string
     */
    protected $connection;

    /**
     * Host of the user
     * 
     * @var string
     */
    public $host;

    /**
     * Current nick of user
     * 
     * @var string
     */
    public $nick;

    /**
     * Server password specified by the user
     * 
     * @var string
     */
    public $password;

    /**
     * User name / ident
     * 
     * @var string
     */
    public $ident;

    /**
     * Real name
     * 
     * @var string
     */
    public $realName;

    /**
     * Configuration of the user
     * 
     * @var \TwIRCd\Configuration
     */
    public $configuration;

    /**
     * Microblogging client this user uses.
     * 
     * @var \TwIRCd\Client
     */
    public $client;

    /**
     * Friends of the user, stored as an array of Friend objects.
     * 
     * @var array
     */
    public $friends;

    /**
     * Create a new user context object from the user connection
     * 
     * @param resource $connection 
     * @return void
     */
    public function __construct( $connection )
    {
        $this->connection = $connection;
    }

    /**
     * Provide property read access
     * 
     * @param string $property 
     * @return void
     */
    public function __get( $property )
    {
        switch ( $property )
        {
            case 'connection':
                return $this->$property;

            default:
                throw new InvalidArgumentException( $property );
        }
    }

    /**
     * Return a string representation of the user
     *
     * Returns a common string representation of the IRC user
     * 
     * @return string
     */
    public function __toString()
    {
        return "{$this->nick}!{$this->ident}@{$this->host}";
    }
}

