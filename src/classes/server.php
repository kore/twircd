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
 * Twitter IRC Server
 * 
 * @package Core
 * @version $Revision$
 * @license http://www.gnu.org/licenses/gpl-3.0.txt GPL
 */
class Server
{
    /**
     * Logger, which is used to log messages
     * 
     * @var \TwIRCd\Logger
     */
    protected $logger;

    /**
     * The IRC server, used to serve the clients
     * 
     * @var \TwIRCd\Irc\Server
     */
    protected $ircServer;

    /**
     * Clients, which fetches the data from a service like Twitter, and 
     * maintains the request queue.
     * 
     * @var array
     */
    protected $clients;

    /**
     * Construct Twitter IRC server
     *
     * Provide a logger and an IRC server implementation.
     * 
     * @param \TwIRCd\Logger $logger 
     * @param \TwIRCd\Irc\Server $ircServer 
     * @return void
     */
    public function __construct( \TwIRCd\Logger $logger, \TwIRCd\Irc\Server $ircServer )
    {
        $this->logger    = $logger;
        $this->ircServer = $ircServer;
        $this->clients   = array();

        $this->registerCallbacks();
    }

    /**
     * Register callbacks for interaction of twitter server with IRC server
     * 
     * @return void
     */
    protected function registerCallbacks()
    {
        $this->ircServer->registerCallback( 'USER', array( $this, 'startup' ) );
        $this->ircServer->registerCallback( 'PRIVMSG', array( $this, 'twitter' ) );
        $this->ircServer->registerCallback( 'JOIN', array( $this, 'addSearch' ) );
        $this->ircServer->registerCallback( 'cycle', array( $this, 'check' ) );
    }

    /**
     * Run the Twitter IRC Server
     * 
     * @return void
     */
    public function run()
    {
        $this->logger->log( E_NOTICE, 'Starting the IRC server.' );
        $this->ircServer->run();
    }

    /**
     * Method called by IRC server in each cycle
     *
     * Method to perform all regular updates, maintaining the twitter queue 
     * etc. Called "very often" by the IRC server.
     * 
     * @return void
     */
    public function check()
    {
        foreach ( $this->clients as $client )
        {
            $messages = $client['client']->getUpdates();

            foreach ( $messages as $message )
            {
                $this->ircServer->sendMessage(
                    $client['user'],
                    $message->from,
                    $message->to,
                    $message->message
                );
            }
        }
    }

    /**
     * Join channels, after user registered on the IRC server
     * 
     * @param Irc\User $user 
     * @param Irc\Message $message 
     * @return void
     */
    public function startup( Irc\User $user, Irc\Message $message )
    {
        $client = new Client\Twitter( $this->logger );
        $client->setCredentials( $user->nick, $user->password );
        $this->clients[$user->nick] = array(
            'user'   => $user,
            'client' => $client,
        );

        $friendList = "@twircd @{$user->nick} " . implode( ' ', array_map(
            function ( $friend )
            {
                return ( $friend->follower ? '+' : '' ) . $friend->name;
            },
            $client->getFriends()
        ) );

        $this->ircServer->send( $user, ":$user JOIN &twitter" );
        foreach ( explode( "\n", wordwrap( $friendList, 400 ) ) as $string )
        {
            // Could not find any proper docs in RFC 2812 or 1459 why the channel 
            // has to be the third parameter in the 353 message
            $this->ircServer->sendServerMessage( $user, "353 = = &twitter :$string" );
        }
        $this->ircServer->sendServerMessage( $user, "366 &twitter :End of NAMES list" );
        $client->queue( 'getTimeline', array( 1234567890 ) );

        // @todo: Join channels for configured searches
    }

    /**
     * A message has been sent
     *
     * The message, which has been received from the user most likely means, 
     * that we should send out a twitter message.
     * 
     * @param Irc\User $user 
     * @param Irc\Message $message 
     * @return void
     */
    public function twitter( Irc\User $user, Irc\Message $message )
    {
        if ( $message->params[0] !== '&twitter' )
        {
            return;
        }

        $this->logger->log( E_NOTICE, "Twitter: " . $message->text );
        // @todo: Implement
    }

    /**
     * A new channel has been joined
     *
     * The user can join new channels, to configure searches for each of those 
     * channels. A new channel means adding a search query for this one.
     * 
     * @param Irc\User $user 
     * @param Irc\Message $message 
     * @return void
     */
    public function addSearch( Irc\User $user, Irc\Message $message )
    {
        // @todo: Update configuration
        // @todo: Join channel
        // @todo: Schedule fetching of search results
    }
}
