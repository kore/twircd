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
 * IRC Message object
 * 
 * @package Core
 * @version $Revision$
 * @license http://www.gnu.org/licenses/gpl-3.0.txt GPL
 */
class Message
{
    /**
     * Nick of the interacting user
     * 
     * @var string
     */
    public $nick;

    /**
     * Ident of the interacting user
     * 
     * @var string
     */
    public $ident;

    /**
     * Host of the interacting user
     * 
     * @var string
     */
    public $host;

    /**
     * Server name, we are interacting with
     * 
     * @var string
     */
    public $server;

    /**
     * IRC command given
     * 
     * @var string
     */
    public $command;

    /**
     * Array with parameters of the command
     * 
     * @var array
     */
    public $params;

    /**
     * Text passed with command
     * 
     * @var string
     */
    public $text;

    /**
     * Parse read string into a message object
     *
     * Parses IRC message, whiich conform to RFC 1459 into a IRC message object 
     * for further handling.
     * 
     * @param TwIRCd\Logger $logger 
     * @param string $string 
     * @return TwIRCd\Irc\Message
     */
    public static function parseClientString( \TwIRCd\Logger $logger, $string )
    {
        if ( preg_match( '(^
                (?: :(?P<host>\\S+) \\s* )?
                (?P<command> [A-Za-z]+ | \\d{3} )
                (?P<params> (?:\\s+ [^:\\s]\\S* )* )
                (?: \\s+: (?P<text>.*) )?
            \\s*$)Sx', $string, $match ) )
        {
            $message = new static();

            // Split up client host
            if ( preg_match('(^(?P<nick>\\S*)!(?P<ident>\\S*)@(?P<host>\\S*))S', $match['host'], $host ) )
            {
                $message->nick  = $host['nick'];
                $message->ident = $host['ident'];
                $message->host  = $host['host'];
            }
            else
            {
                $msg['server'] = $match['host'];
            }
                   
            $message->command = $match['command'];
            $message->params  = preg_split( '(\\s+)', trim( $match['params'] ) );
            $message->text    = isset( $match['text'] ) ? $match['text'] : null;

            return $message;
        }
        else
        {
            $logger->log( E_ERROR, "Could not parse: $string" );
            return null;
        }
    }
}

