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
 * Mapper class
 *
 * Creates configuration and microblogging clients based on provided user 
 * information or other environmental information.
 *
 * @package Core
 * @version $Revision$
 * @license http://www.gnu.org/licenses/gpl-3.0.txt GPL
 */
abstract class Mapper
{
    /**
     * Contained logger
     * 
     * @var \TwIRCd\Logger
     */
    protected $logger;

    /**
     * Construct mapper from logger
     * 
     * @param \TwIRCd\Logger $logger 
     * @return void
     */
    public function __construct( \TwIRCd\Logger $logger )
    {
        $this->logger = $logger;
    }

    /**
     * Initialize user account
     *
     * Initializes the microblogging client and the client configuration.
     * 
     * @param Irc\User $user 
     * @return void
     */
    abstract public function initializeUserAccount( \TwIRCd\Irc\User $user );
}

