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
 * IRC Server
 * 
 * @package Core
 * @version $Revision$
 * @license http://www.gnu.org/licenses/gpl-3.0.txt GPL
 */
class Server
{
    /**
     * Registered callbacks
     *
     * Callbacks are used, to notify other programs about events happening on 
     * the IRC server, so they can perform their actions.
     * 
     * @var array
     */
    protected $callbacks = array();

    /**
     * Array of user contextes
     * 
     * @var array
     */
    protected $users = array();

    /**
     * Logger, which is used to log messages
     * 
     * @var \TwIRCd\Logger
     */
    protected $logger;

    /**
     * IP the server listens on
     * 
     * @var string
     */
    protected $ip;

    /**
     * The port the server listens on
     * 
     * @var port
     */
    protected $port;

    /**
     * Main socket handling the listening.
     * 
     * @var socket
     */
    protected $socket = null;

    /**
     * Construct Twitter IRC server
     *
     * The constructor parameters define the IP and port the client should 
     * listen on.
     * 
     * @param \TwIRCd\Logger $logger 
     * @param string $ip 
     * @param int $port 
     * @return void
     */
    public function __construct( \TwIRCd\Logger $logger, $ip = '127.0.0.1', $port = 6667 )
    {
        $this->logger = $logger;
        $this->ip     = $ip;
        $this->port   = $port;

        // Register own callbacks, which are required to provide a working IRC 
        // server
        $this->callbacks = array(
            'PASS' => array(
                array( $this, 'setUserPassword' ),
            ),
            'USER' => array(
                array( $this, 'registerUser' ),
            ),
            'NICK' => array(
                array( $this, 'changeNick' ),
            ),
            'QUIT' => array(
                array( $this, 'disconnectUser' ),
            ),
            'PING' => array(
                array( $this, 'pong' ),
            ),
        );
    }

    /**
     * Close all sockets on exit
     * 
     * @return void
     */
    public function __destruct()
    {
        foreach ( $this->users as $user )
        {
            socket_close( $user->connection );
        }

        if ( $this->socket !== null )
        {
            socket_close( $this->socket );
        }
    }

    /**
     * Register callback
     *
     * Register a callback for a specific event. The available callbacks are:
     *
     * - JOIN
     * - MESSAGE
     *
     * The callbacks should be of the common PHP callback datatype.
     * 
     * @param string $event 
     * @param callback $callback 
     * @return void
     */
    public function registerCallback( $event, $callback )
    {
        $this->callbacks[$event][] = $callback;
    }

    /**
     * Run the server
     *
     * Runs the server. The method will run indefinetly. The callbacks are 
     * called for all events, which occured while running. The server can be 
     * stopped by sending a common KILL signal to it.
     * 
     * @return void
     */
    public function run()
    {
        $socket = $this->bind();
        do {
            // Check for new clients
            if ( $client = @socket_accept( $socket ) )
            {
                socket_set_nonblock( $client );
                socket_getpeername( $client, $address );
                $this->logger->log( E_NOTICE, "Client connected from $address." );
                $this->users[] = $user = new User( $client );
                $user->host = gethostbyaddr( $address );
            }

            // Process incoming messages
            foreach ( $this->users as $user )
            {
                $this->processMessages( $user );
            }

            // Cleanup and sleep a bit
            gc_collect_cycles();
            usleep( 100 * 1000 );

        } while ( $socket );
    }

    /**
     * Establish listening socket on the configured interface
     * 
     * @return resource
     */
    protected function bind()
    {
        $this->logger->log( E_NOTICE, "Binding socket to {$this->ip}:{$this->port}." );
        $socket = socket_create( AF_INET, SOCK_STREAM, SOL_TCP );

        $success = @socket_bind( $socket, $this->ip, $this->port );
        while( !$success ) 
        {
            $this->logger->log( E_WARNING, "Failed binding to {$this->ip}:{$this->port}, wait and retry." );
            sleep( 5 );
            $success = @socket_bind( $socket, $this->ip, $this->port );
        }

        socket_listen( $socket );
        socket_set_nonblock( $socket );
        return $socket;
    }

    /**
     * Process new messages on connection
     * 
     * @param resource $connection 
     * @return void
     */
    protected function processMessages( User $user )
    {
        $data = '';
        while ( @socket_recv( $user->connection, $buffer, 1024, MSG_DONTWAIT ) )
        {
            $data .= $buffer;
        }
        $lines = preg_split( '(\r\n|\r|\n)', trim( $data ) );

        if ( empty( $data ) )
        {
            return;
        }

        foreach ( $lines as $line )
        {
            $this->logger->log( E_NOTICE, "Recieved from client: $line" );
            $message = Message::parseClientString( $this->logger, $line );

            if ( !isset( $this->callbacks[$message->command] ) )
            {
                $this->logger->log( E_NOTICE, "Unhandled command {$message->command}." );
                continue;
            }

            foreach ( $this->callbacks[$message->command] as $callback )
            {
                call_user_func( $callback, $user, $message );
            }
        }
    }

    /**
     * Send IRC command to user
     * 
     * @param User $user 
     * @param mixed $message 
     * @return void
     */
    public function send( User $user, $message )
    {
        $this->logger->log( E_NOTICE, "Response sent to user: $message" );
        socket_write( $user->connection, trim( $message ) . "\r\n" );
    }

    /**
     * Send IRC command to user
     * 
     * @param User $user 
     * @param mixed $message 
     * @return void
     */
    public function sendServerMessage( User $user, $message )
    {
        $this->send( $user, ':twircd '. $message );
    }

    /**
     * Change nick of user
     * 
     * @param User $user 
     * @param Message $message 
     * @return void
     */
    protected function changeNick( User $user, Message $message )
    {
        $this->logger->log( E_NOTICE, "User changed nick to: {$message->params[0]}." );
        $user->nick = $message->params[0];
    }

    /**
     * Set password for user
     * 
     * @param User $user 
     * @param Message $message 
     * @return void
     */
    protected function setUserPassword( User $user, Message $message )
    {
        $user->password = $message->params[0];
    }

    /**
     * Register user with server
     * 
     * @param User $user 
     * @param Message $message 
     * @return void
     */
    protected function registerUser( User $user, Message $message )
    {
        $user->ident    = $message->params[0];
        $user->realName = $message->text;

        $this->sendServerMessage( $user, "001 {$user->nick} :Welcome to the Twitter IRC Server." );
        $this->sendServerMessage( $user, "002 {$user->nick} :Your host is {$this->ip} [{$this->ip}/ {$this->port}], running twircd." );
        $this->sendServerMessage( $user, "003 {$user->nick} :This server was created just for you." );
        $this->sendServerMessage( $user, "004 {$user->nick} :twircd 0.0.1 o t" );
    }

    /**
     * Pong to recieved ping from client
     * 
     * @param User $user 
     * @param Message $message 
     * @return void
     */
    protected function pong( User $user, Message $message )
    {
        $this->sendServerMessage( $user, 'PONG ' . implode( ' ', $message->params ) );
    }

    /**
     * Disconnect client, after it sent a quit message.
     * 
     * @param User $user 
     * @param Message $message 
     * @return void
     */
    protected function disconnectUser( User $user, Message $message )
    {
        foreach ( $this->users as $nr => $client )
        {
            if ( $client === $user )
            {
                $this->logger->log( E_NOTICE, "Disconnecting client {$user->nick}." );
                $this->sendServerMessage( $user, 'QUIT :' . $message->text ?: 'Client exited' );
                socket_close( $user->connection );
                unset( $this->users[$nr] );
            }
        }
    }
}
