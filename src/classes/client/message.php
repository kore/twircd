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
 * Message, whcih can be passed back to the IRC server
 *
 * @package Core
 * @version $Revision$
 * @license http://www.gnu.org/licenses/gpl-3.0.txt GPL
 */
class Message
{
    /**
     * Author of the message
     * 
     * @var string
     */
    public $from;

    /**
     * Receiver of the message. May be a channel, or user
     * 
     * @var string
     */
    public $to;

    /**
     * Text of message
     * 
     * @var string
     */
    public $message;

    /**
     * Construct message from its parameters.
     * 
     * @param string $from 
     * @param string $to 
     * @param string $message 
     * @return void
     */
    public function __construct( $from, $to, $message )
    {
        $this->from    = $from;
        $this->to      = $to;
        $this->message = $message;
    }
}

