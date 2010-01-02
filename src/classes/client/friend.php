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

namespace TwIRCd\Client;

/**
 * Strucut representing a microblogging "friend"
 *
 * @package Core
 * @version $Revision$
 * @license http://www.gnu.org/licenses/gpl-3.0.txt GPL
 */
class Friend
{
    /**
     * Identifier of the friend
     * 
     * @var string
     */
    public $name;

    /**
     * Last status message of the friend
     * 
     * @var string
     */
    public $status = null;

    /**
     * Real name of the friend
     * 
     * @var string
     */
    public $realName = null;

    /**
     * Boolean indicator if the friend follows you back.
     * 
     * @var bool
     */
    public $follower = false;

    /**
     * Construct friend from its name
     * 
     * @param string $name 
     * @return void
     */
    public function __construct( $name, $status = null, $realName = null, $follower = false )
    {
        $this->name     = $name;
        $this->status   = $status;
        $this->realName = $realName;
        $this->follower = $follower;
    }
}

