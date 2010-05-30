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
     * The IRC server, used to serve the users
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
    protected $users;

    /**
     * User to client mapper and configuration initializer
     * 
     * @var \TwIRCd\Mapper
     */
    protected $mapper;

    /**
     * Timestamp of last friend list update
     * 
     * @var int
     */
    protected $lastFriendListUpdate;

    /**
     * Construct Twitter IRC server
     *
     * Provide a logger and an IRC server implementation.
     * 
     * @param \TwIRCd\Logger $logger 
     * @param \TwIRCd\Irc\Server $ircServer 
     * @return void
     */
    public function __construct( \TwIRCd\Logger $logger, \TwIRCd\Irc\Server $ircServer, \TwIRCd\Mapper $mapper )
    {
        $this->logger    = $logger;
        $this->ircServer = $ircServer;
        $this->mapper    = $mapper;
        $this->users     = array();

        $this->registerCallbacks();
    }

    /**
     * Register callbacks for interaction of twitter server with IRC server
     * 
     * @return void
     */
    protected function registerCallbacks()
    {
        $this->ircServer->registerCallback( 'USER',     array( $this, 'startup' ) );
        $this->ircServer->registerCallback( 'PRIVMSG',  array( $this, 'twitter' ) );
        $this->ircServer->registerCallback( 'PRIVMSG',  array( $this, 'directMessage' ) );
        $this->ircServer->registerCallback( 'PRIVMSG',  array( $this, 'command' ) );
        $this->ircServer->registerCallback( 'JOIN',     array( $this, 'addSearch' ) );
        $this->ircServer->registerCallback( 'JOIN',     array( $this, 'addGroup' ) );
        $this->ircServer->registerCallback( 'PART',     array( $this, 'removeSearch' ) );
        $this->ircServer->registerCallback( 'PART',     array( $this, 'removeGroup' ) );
        $this->ircServer->registerCallback( 'TOPIC',    array( $this, 'updateSearch' ) );
        $this->ircServer->registerCallback( 'WHO',      array( $this, 'listFriends' ) );
        $this->ircServer->registerCallback( 'WHOIS',    array( $this, 'getFriendInfo' ) );
        $this->ircServer->registerCallback( 'INVITE',   array( $this, 'followUser' ) );
        $this->ircServer->registerCallback( 'INVITE',   array( $this, 'inviteToGroup' ) );
        $this->ircServer->registerCallback( 'KICK',     array( $this, 'unfollowUser' ) );
        $this->ircServer->registerCallback( 'KICK',     array( $this, 'removeFromGroup' ) );
        $this->ircServer->registerCallback( 'cycle',    array( $this, 'check' ) );
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
        foreach ( $this->users as $user )
        {
            $messages = $user->client->getUpdates();

            foreach ( $messages as $message )
            {
                $this->ircServer->sendMessage(
                    $user,
                    $message->from,
                    $message->to,
                    $message->message
                );

                // Check if message should additionally be proxied into a group 
                // channel.
                //
                // @TODO: Refactor this.
                if ( $message->to === '&twitter' )
                {
                    foreach ( $user->configuration->getGroups() as $channel => $friends )
                    {
                        foreach ( $friends as $friend )
                        {
                            if ( preg_match( '(^' . preg_quote( $friend ) . '!)', $message->from ) )
                            {
                                $this->ircServer->sendMessage(
                                    $user,
                                    $message->from,
                                    $channel,
                                    $message->message
                                );
                            }
                        }
                    }
                }
            }

            $this->checkFriendListUpdate( $user );
        }
    }

    /**
     * Check for an updated friend list
     * 
     * @param Irc\User $user 
     * @return void
     */
    protected function checkFriendListUpdate( Irc\User $user )
    {
        if ( ( $this->lastFriendListUpdate + 600 ) > time() )
        {
            // Only update friend list every ten minutes
            return;
        }

        $this->logger->log( E_NOTICE, 'Check for friend list updates.' );
        $friends      = $user->client->getFriends();
        if ( !count( $friends ) )
        {
            // This is likely a service failure, just abort.
            return;
        }

        // Set operations of old and new friends lists
        $intersection = array_intersect( array_keys( $friends ), array_keys( $user->friends ) );
               
        $followed     = array_diff( array_keys( $friends ), $intersection );
        $unfollowed   = array_diff( array_keys( $user->friends ), $intersection );

        // Let users join, which are now followed by the user
        foreach ( $followed as $friend )
        {
            $this->logger->log( E_NOTICE, "You are no following $friend." );
            $this->ircServer->send( $user, ":$friend!$friend@twitter.com JOIN :&twitter" );
        }

        // Let users part, which the user does not follow anymore
        foreach ( $unfollowed as $friend )
        {
            $this->logger->log( E_NOTICE, "You are not following $friend any more." );
            $this->ircServer->send( $user, ":$friend!$friend@twitter.com PART &twitter :Unfollowed" );
        }

        // Update stored friend list
        $user->friends = $friends;
        $this->lastFriendListUpdate = time();
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
        if ( isset( $this->users[$user->nick] ) )
        {
            $this->logger->log( E_WARNING, 'Ignoring user reregistration.' );
            return;
        }

        $this->mapper->initializeUserAccount( $user );
        $this->users[$user->nick] = $user;

        // Join default &twitter channel, with all friends listed as users
        $friendList = "@twircd " . implode( ' ', array_map(
            function ( $friend )
            {
                return ( $friend->follower ? '+' : '' ) . $friend->name;
            },
            $user->friends = $user->client->getFriends()
        ) );
        $this->lastFriendListUpdate = time();
        $this->joinChannel( $user, '&twitter', $friendList, "Your personal TwIRCd main channel | Just write something to tweet." );

        // Queue default user updates
        $user->client->queue( 'getTimeline' );
        $user->client->queue( 'getMentions' );
        $user->client->queue( 'getDirectMessages' );

        // Join channels for configured searches
        foreach ( $user->configuration->getSearches() as $channel => $search )
        {
            $this->joinChannel( $user, $channel, '', $search );
            $user->client->queue( 'getSearchResults', array( $channel ) );
        }

        // Join channels for configured groups
        foreach ( $user->configuration->getGroups() as $channel => $users )
        {
            $this->joinChannel( $user, $channel, implode( ' ', $users ), "Group $channel" );
        }
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

        try
        {
            $this->logger->log( E_NOTICE, "Twitter: " . $message->params[1] );
            $user->client->updateStatus( $message->params[1] );
        }
        catch ( ConnectionException $e )
        {
            $this->ircServer->sendMessage( $user, 'twircd', '&twitter', 'Could not send update: ' . $e->getMessage() );
        }
        catch ( LengthException $e )
        {
            $this->ircServer->sendMessage( $user, 'twircd', '&twitter', $e->getMessage() );
        }
    }

    /**
     * Receive a command message.
     *
     * Command messages are send to the "twircd" user via query.
     * 
     * @TODO Refactor to have a real command structure. Should be client 
     *       specific, except for a few global commands, like the currently
     *       implemented set/get.
     * @param Irc\User $user 
     * @param Irc\Message $message 
     * @return void
     */
    public function command( Irc\User $user, Irc\Message $message )
    {
        if ( $message->params[0] !== 'twircd' )
        {
            return;
        }

        $this->logger->log( E_NOTICE, "Command: " . $message->params[1] );

        if ( preg_match( '((set|get)\s*([^\s]+)\s*(.*)$)i', $message->params[1], $matches ) == 0 )
        {
            $this->logger->log( E_WARNING, "Unkown command: " . $message->params[1] );
        }
        
        switch( strtolower( $matches[1] ) )
        {
            case 'set':
                $user->configuration->setValue( $matches[2], $matches[3] );
                $this->ircServer->sendMessage(
                    $user,
                    'twircd',
                    $user->nick,
                    "Successfully set '{$matches[2]}' to '{$matches[3]}'."
                );
                $this->logger->log( E_NOTICE, "Set '{$matches[2]}' to '{$matches[3]}'." );
                break;
            case 'get':
                $this->ircServer->sendMessage(
                    $user,
                    'twircd',
                    $user->nick,
                    "Value for key '{$matches[2]}' is '"
                        . ( $val = $user->configuration->getValue( $matches[2], '' ) )
                        . "'."
                );
                $this->logger->log( E_NOTICE, "Returned '{$matches[2]}' is '$val'." );
                break;
        }
    }

    /**
     * A direct message should be sent
     *
     * If the target of the message received from the user is in his friends 
     * list, it means this is a direct message.
     * 
     * @param Irc\User $user 
     * @param Irc\Message $message 
     * @return void
     */
    public function directMessage( Irc\User $user, Irc\Message $message )
    {
        $target = $message->params[0];
        if ( !isset( $user->friends[$target] ) )
        {
            return;
        }

        try
        {
            $this->logger->log( E_NOTICE, "Direct message to $target: " . $message->params[1] );
            $user->client->sendDirectMessage( $target, $message->params[1] );
        }
        catch ( ConnectionException $e )
        {
            $this->ircServer->sendMessage( $user, 'twircd', '&twitter', 'Could not send direct message: ' . $e->getMessage() );
        }
        catch ( LengthException $e )
        {
            $this->ircServer->sendMessage( $user, 'twircd', '&twitter', $e->getMessage() );
        }
    }

    /**
     * List friends
     *
     * Provide a list of all friends for the requested channel
     * 
     * @param Irc\User $user 
     * @param Irc\Message $message 
     * @return void
     */
    public function listFriends( Irc\User $user, Irc\Message $message )
    {
        // WHO response for the user itself
        $this->ircServer->sendServerMessage(
            $user,
            "352 {$user->nick} {$message->params[0]} {$user->ident} {$user->host} twircd {$user->nick} H@ :0 {$user->realName}"
        );

        if ( $message->params[0] === '&twitter' )
        {
            // List all friends as away for the &twitter channel
            foreach ( $user->friends as $friend )
            {
                $this->ircServer->sendServerMessage( 
                    $user,
                    "352 {$user->nick} &twitter {$friend->name} twitter.com twircd {$friend->name} G :0 {$friend->realName}"
                );
            }
        }

        // End of responses
        $this->ircServer->sendServerMessage(
            $user,
            "315 {$user->nick} {$message->params[0]} :End of WHO list"
        );
    }

    /**
     * Get information about friend
     *
     * Get detailed information about the friend, as requested by WHOIS.
     * 
     * @param Irc\User $user 
     * @param Irc\Message $message 
     * @return void
     */
    public function getFriendInfo( Irc\User $user, Irc\Message $message )
    {
        if ( $message->params[0] === $user->nick )
        {
            $this->ircServer->sendServerMessage( $user, "311 {$user->nick} {$user->nick} {$user->ident} {$user->host} * :{$user->realName}" );
            $this->ircServer->sendServerMessage( $user, "318 {$user->nick} {$user->nick} :End of /WHOIS list." );
            return;
        }

        if ( !isset( $user->friends[$message->params[0]] ) )
        {
            $this->ircServer->sendServerMessage( $user, "401 {$user->nick} {$message->params[0]} :No such nick/channel" );
            return;
        }

        $friend = $user->friends[$message->params[0]];
        $this->ircServer->sendServerMessage( $user, "311 {$user->nick} {$friend->name} {$friend->name} twitter.com * :{$friend->realName}" );
        $this->ircServer->sendServerMessage( $user, "301 :{$friend->status}" );
        $this->ircServer->sendServerMessage( $user, "318 {$user->nick} {$friend->name} :End of /WHOIS list." );
    }

    /**
     * Somebody has been invited
     *
     * If an invite has been sent in the &twitter channel, this means a 
     * following request issued by the user.
     * 
     * @param Irc\User $user 
     * @param Irc\Message $message 
     * @return void
     */
    public function followUser( Irc\User $user, Irc\Message $message )
    {
        if ( $message->params[1] !== '&twitter' )
        {
            return;
        }

        try
        {
            $user->client->followUser( $message->params[0] );
        }
        catch ( ConnectionException $e )
        {
            $this->ircServer->sendMessage( $user, 'twircd', '&twitter', 'Could not follow user: ' . $e->getMessage() );
        }
    }

    /**
     * Somebody has been kicked
     *
     * If an kick has been sent in the &twitter channel, this means an
     * unfollowing request issued by the user.
     * 
     * @param Irc\User $user 
     * @param Irc\Message $message 
     * @return void
     */
    public function unfollowUser( Irc\User $user, Irc\Message $message )
    {
        if ( $message->params[0] !== '&twitter' )
        {
            return;
        }

        try
        {
            $user->client->unfollowUser( $message->params[1] );
        }
        catch ( ConnectionException $e )
        {
            $this->ircServer->sendMessage( $user, 'twircd', '&twitter', 'Could not unfollow user: ' . $e->getMessage() );
        }
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
        $channel  = $message->params[0];
        $searches = $user->configuration->getSearches();
        if ( ( $channel[0] !== '#' ) ||
             isset( $searches[$channel] ) )
        {
            return;
        }

        $user->configuration->setSearch( $channel, $channel );
        $this->joinChannel( $user, $channel, '', $channel );
        $user->client->queue( 'getSearchResults', array( $channel ) );
        $this->logger->log( E_NOTICE, "Added search channel $channel." );
    }

    /**
     * A new channel has been parted
     *
     * If the users parts a search channel this means, we should remove the 
     * search from the storage and tell the user about the parted channel.
     * 
     * @param Irc\User $user 
     * @param Irc\Message $message 
     * @return void
     */
    public function removeSearch( Irc\User $user, Irc\Message $message )
    {
        $channel  = $message->params[0];
        $searches = $user->configuration->getSearches();
        if ( ( $channel[0] !== '#' ) ||
             !isset( $searches[$channel] ) )
        {
            return;
        }

        $user->configuration->removeSearch( $channel );
        $this->ircServer->send( $user, ":$user PART $channel :Search removed" );
        $this->logger->log( E_NOTICE, "Removed search channel $channel." );
    }

    /**
     * The topic has been set
     *
     * If the topic has been updated for one of the search channels, we need to 
     * update the search parameters and tell the client about the topic update.
     * 
     * @param Irc\User $user 
     * @param Irc\Message $message 
     * @return void
     */
    public function updateSearch( Irc\User $user, Irc\Message $message )
    {
        $channel = $message->params[0];
        if ( $channel[0] !== '#' )
        {
            return;
        }

        $user->configuration->setSearch( $channel, $message->params[1] );
        $this->ircServer->send( $user, ":$user TOPIC $channel :{$message->params[1]}" );
        $this->logger->log( E_NOTICE, "Updated search for channel $channel to: {$message->params[1]}" );
    }

    /**
     * A new channel has been joined
     *
     * The user can join new channels, to configure groups for each of those 
     * channels. A new channel means adding an empty group.
     * 
     * @param Irc\User $user 
     * @param Irc\Message $message 
     * @return void
     */
    public function addGroup( Irc\User $user, Irc\Message $message )
    {
        $channel = $message->params[0];
        $groups  = $user->configuration->getGroups();
        if ( ( $channel[0] !== '&' ) ||
             ( $channel === '&twitter' ) ||
             isset( $groups[$channel] ) )
        {
            return;
        }

        $user->configuration->setGroup( $channel );
        $this->joinChannel( $user, $channel, '', "Group $channel" );
        $this->logger->log( E_NOTICE, "Added group channel $channel." );
    }

    /**
     * A new channel has been parted
     *
     * If the users parts a group channel this means, we should remove the 
     * group from the storage and tell the user about the parted channel.
     * 
     * @param Irc\User $user 
     * @param Irc\Message $message 
     * @return void
     */
    public function removeGroup( Irc\User $user, Irc\Message $message )
    {
        $channel = $message->params[0];
        $groups  = $user->configuration->getGroups();
        if ( ( $channel[0] !== '&' ) ||
             ( $channel === '&twitter' ) ||
             !isset( $groups[$channel] ) )
        {
            return;
        }

        $user->configuration->removeGroup( $channel );
        $this->ircServer->send( $user, ":$user PART $channel :Group removed" );
        $this->logger->log( E_NOTICE, "Removed group channel $channel." );
    }

    /**
     * Somebody has been invited
     *
     * If an invite has been sent in the &twitter channel, this means a 
     * following request issued by the user.
     * 
     * @param Irc\User $user 
     * @param Irc\Message $message 
     * @return void
     */
    public function inviteToGroup( Irc\User $user, Irc\Message $message )
    {
        $groups  = $user->configuration->getGroups();
        $channel = $message->params[1];
        if ( ( $channel === '&twitter' ) ||
             !isset( $groups[$channel] ) )
        {
            return;
        }

        $groups[$channel][] = $friend = $message->params[0];
        $groups[$channel] = array_unique( $groups[$channel] );
        $user->configuration->setGroup( $channel, $groups[$channel] );
        $this->logger->log( E_NOTICE, "Added $friend to group $channel." );
        $this->ircServer->send( $user, ":$friend!$friend@twitter.com JOIN :$channel" );
    }

    /**
     * Somebody has been kicked
     *
     * If an kick has been sent in the &twitter channel, this means an
     * unfollowing request issued by the user.
     * 
     * @param Irc\User $user 
     * @param Irc\Message $message 
     * @return void
     */
    public function removeFromGroup( Irc\User $user, Irc\Message $message )
    {
        $groups  = $user->configuration->getGroups();
        $channel = $message->params[0];
        if ( ( $channel === '&twitter' ) ||
             !isset( $groups[$channel] ) )
        {
            return;
        }

        if ( ( $key = array_search( $friend = $message->params[1], $groups[$channel] ) ) !== false )
        {
            unset( $groups[$channel][$key] );
            $user->configuration->setGroup( $channel, $groups[$channel] );
            $this->logger->log( E_NOTICE, "Removed $friend from group $channel." );
            $this->ircServer->send( $user, ":$friend!$friend@twitter.com PART $channel :Removed" );
        }
    }

    /**
     * Join IRC channel
     *
     * Let the user join an IRC channel. Optionally a list of other users may 
     * be provided, which are also in the channel, and will be reported as 
     * such.
     * 
     * @param Irc\User $user 
     * @param string $channel 
     * @param string $users 
     * @param string $topic 
     * @return void
     */
    protected function joinChannel( Irc\User $user, $channel, $users = '', $topic = '' )
    {
        $users = "@{$user->nick} $users";
        $this->ircServer->send( $user, ":$user JOIN :$channel" );
        foreach ( explode( "\n", wordwrap( $users, 400 ) ) as $string )
        {
            $this->ircServer->sendServerMessage( $user, "353 {$user->nick} = $channel :$string" );
        }
        $this->ircServer->sendServerMessage( $user, "366 {$user->nick} $channel :End of NAMES list" );

        if ( empty( $topic ) )
        {
            $this->ircServer->sendServerMessage( $user, "331 {$user->nick} $channel :No topic is set" );
        }
        else
        {
            $this->ircServer->sendServerMessage( $user, "332 {$user->nick} $channel :$topic" );
        }
    }
}
