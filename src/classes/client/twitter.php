<?php
/**
 * This file is part of twitter2ii
 *
 * twitter2ii is free software; you can redistribute it and/or modify it under
 * the terms of the GNU Lesser General Public License as published by the Free
 * Software Foundation; version 3 of the License.
 *
 * twitter2ii is distributed in the hope that it will be useful, but WITHOUT ANY
 * WARRANTY; without even the implied warranty of MERCHANTABILITY or FITNESS
 * FOR A PARTICULAR PURPOSE.  See the GNU Lesser General Public License for
 * more details.
 *
 * You should have received a copy of the GNU Lesser General Public License
 * along with twitter2ii; if not, write to the Free Software Foundation, Inc., 51
 * Franklin St, Fifth Floor, Boston, MA  02110-1301  USA
 *
 * @package Core
 * @version $Revision: 999 $
 * @license http://www.gnu.org/licenses/lgpl-3.0.txt LGPL
 */

/**
 * Twitter microblogging client
 */
class iiTwitterClient extends iiClient
{
    /**
     * Service base URL
     * 
     * @var string
     */
    protected $baseUrl = 'twitter.com';

    /**
     * Service base search url URL
     * 
     * @var string
     */
    protected $searchUrl = 'http://search.twitter.com/search.atom?q=%s';

    /**
     * Key identifying the service
     * 
     * @var string
     */
    protected $key = 'twitter';

    /**
     * Twitter search terms, which are listened to.
     * 
     * @var array
     */
    protected $searches;

    /**
     * Create microblogging client from user credentials
     *
     * The twitter client optionally may receive any number of search terms,
     * which are listend to.
     *
     * @param iiCredentials $credentials 
     * @param iiStorage $storage
     * @param array $searches
     * @return void
     */
    public function __construct( iiCredentials $credentials, iiStorage $storage, array $searches = array() )
    {
        parent::__construct( $credentials, $storage );
        $this->searches = $searches;
    }

    /**
     * Get updates from friends timeline
     *
     * Receive all updates from the twitter friends timeline, which includes
     * your own updates, as well all updates from the "friends" you follow.
     * 
     * @return array
     */
    protected function fetchFriendsTimeline()
    {
        $lastMessage = $this->storage->get( $key = $this->key . '/' . $this->credentials->user );

        // Receive all new messages
        //
        // Silence error messages about connection failure or similar, those are
        // somehow expected with twitter.
        $fp = @fopen(
            $url = 'http://' . $this->credentials->user . ':' . $this->credentials->password . '@' . $this->baseUrl . '/statuses/friends_timeline.json?' . 
            ( ( $lastMessage === false ) ?
                http_build_query( array(
                    'count' => 10,
                ) ) :
                http_build_query( array(
                    'since' => date( DATE_RFC822, $lastMessage + 1 ),
                    'count' => 10,
                ) )
            ),
            'r', false,
            stream_context_create(
                array(
                    'http' => array(
                        'method'        => 'GET',
                        'ignore_errors' => true,
                    ),
                )
            )
        );

        if ( $fp === false )
        {
            throw new iiClientException( 'Could not connect.' );
        }

        // Read all the returned stuff
        $data = '';
        while ( !feof( $fp ) )
        {
            $data .= fread( $fp, 1024 );
        }
        fclose( $fp );
        $data = json_decode( $data );

        if ( isset( $data->error ) )
        {
            // On error, exit with error code
            throw new iiClientException(  $data->error );
        }

        $messages = array();
        if ( count( $data ) && is_array( $data ) )
        {
            $data = array_reverse( $data );
            foreach( $data as $message )
            {
                // Twitter some times still sends all messages, even only new
                // message since some date are requested, so we need to recheck
                // that manually.
                $date = new DateTime( $message->created_at );
                if ( $date->getTimestamp() <= $lastMessage )
                {
                    continue;
                }

                $messages[] = $update = new iiMessage(
                    $date,
                    $message->user->screen_name,
                    $this->unfoldUrls( html_entity_decode( $message->text ) ),
                    $message->user->name
                );
            }

            // Store date of last message received
            if ( isset( $update ) )
            {
                $this->storage->store( $key, $update->date->getTimestamp() );
            }
        }

        return $messages;
    }

    /**
     * Convert search term feeds to messages
     *
     * Fetch the RSS feed for the given search term and convert all messages in
     * the result, which are not posted by "friends" since the last fetch to
     * message objects, which thena re returned.
     *
     * Returns an array of iiMessage objects.
     *
     * @param string $term
     * @return array
     */
    public function fetchSearch( $term )
    {
        $lastMessage = $this->storage->get( $key = $this->key . '/' . $this->credentials->user . '_search_' . preg_replace( '([^A-Za-z]+)', '', $term ) );
        $friends = $this->getFriends();

        // Receive all new messages from RSS feed
        // Use internal error handling to handle XML errors manually.
        $oldXmlErrorHandling = libxml_use_internal_errors( true );
        libxml_clear_errors();
        $feed = @simplexml_load_file( sprintf( $this->searchUrl, urlencode( $term ) ) );
        libxml_clear_errors();
        libxml_use_internal_errors( $oldXmlErrorHandling );

        // Check, that we got some valid data from feed.
        if ( !$feed || 
             !count( $feed->entry ) )
        {
            throw new iiClientException( 'Could not fetch data.' );
        }

        $messages = array();
        $newLast  = $lastMessage;
        foreach ( $feed->entry as $item )
        {
            $date = new DateTime( (string) $item->published );
            if ( $date->getTimestamp() <= $lastMessage )
            {
                continue;
            }

            // Split up user name
            if ( preg_match( '(^(?P<nick>\S+)\s+\((?P<user>.*)\)\s*$)s', (string) $item->author->name, $match ) )
            {
                if ( array_search( $match['nick'], $friends ) )
                {
                    // Do not show messages in searches from friends.
                    continue;
                }

                $update = new iiMessage(
                    $date,
                    $match['nick'],
                    $this->unfoldUrls( trim( (string) $item->title ) ),
                    $match['user']
                );
            }
            else
            {
                // No valid entry found
                continue;
            }

            $messages[] = $update;
            $newLast    = max( $newLast, $date->getTimestamp() );
            break;
        }

        // Store date of last message received
        $this->storage->store( $key, $newLast );

        return $messages;
    }

    /**
     * Fetch new messages for all search terms
     * 
     * @return array
     */
    protected function fetchSearches()
    {
        $messages = array();
        foreach ( $this->searches as $term )
        {
            $messages = array_merge(
                $messages,
                $this->fetchSearch( $term )
            );
        }
        return $messages;
    }

    /**
     * Receive new messages
     *
     * Receive new messages from microblogging service.
     *
     * Returns an array of iiMessage objects.
     *
     * @return array
     */
    public function getNewMessages()
    {
        return array_merge(
            $this->fetchFriendsTimeline(),
            $this->fetchSearches()
        );
    }

    /**
     * Send message
     *
     * Send given string as a message using the given microblogging service.
     * There might be some restrictions on the message, depending on the
     * service. Violating these an exception might be thrown.
     * 
     * @param string $string 
     * @return void
     */
    public function sendMessage( $string )
    {
        // Try to shorten all included URLs, if message is too long otherwise
        if ( strlen( $string ) > 140 )
        {
            $string = $this->shortenUrls( $string );
        }

        // Skip messages, which aree too long for twitter, and inform the user
        if ( strlen( $string ) > 140 )
        {
            throw new iiClientException(
                sprintf( "Skipping too long message (%d characters), overlapping part: '%s'\n",
                    strlen( $string ),
                    substr( trim( $string ), 140 )
                )
            );
        }

        // Send using twitter REST API
        //
        // Silence error messages about connection failure or similar, those are
        // somehow expected with twitter.
        $fp = @fopen(
            $url = 'http://' . $this->credentials->user . ':' . $this->credentials->password . '@' . $this->baseUrl . '/statuses/update.json', 'r', false,
            stream_context_create(
                array(
                    'http' => array(
                        'method'        => 'POST',
                        'content'       => http_build_query( array(
                            'status' => $string,
                        ) ),
                        'ignore_errors' => true,
                    ),
                )
            )
        );

        if ( $fp === false )
        {
            throw new iiClientException( "Could not connect." );
        }

        // Check the return value
        $return = '';
        while ( !feof( $fp ) )
        {
            $return .= fread( $fp, 1024 );
        }

        // Check if an error has been returned
        $struct = json_decode( $return );
        if ( isset( $struct->error ) )
        {
            throw new iiClientException( $struct->error );
        }

        fclose( $fp );
    }

    /**
     * Get list of people, who you are following on twitter.
     * 
     * @return array
     */
    protected function getFriends()
    {
        // Receive friend list, if not already fetched, and update friend list
        // every hour.
        if ( ( ( $friends = $this->storage->get( $key = $this->key . '/' . $this->credentials->user . '_friends' ) ) === false ) ||
             ( $friends['lastUpdate'] < ( time() - 3600 ) ) )
        {
            $json = file_get_contents( 'http://' . $this->credentials->user . ':' . $this->credentials->password . '@' . $this->baseUrl . '/statuses/friends.json' );
            if ( !$json )
            {
                return array();
            }

            $json = json_decode( $json, true );
            
            $friends = array(
                'lastUpdate' => time(),
                'friends'    => array(),
            );
            foreach( $json as $friend )
            {
                $friends['friends'][] = $friend['screen_name'];
            }

            $this->storage->store( $key, $friends );
        }

        return $friends['friends'];
    }

    /**
     * Get client name
     *
     * Get a name of the client, which is mainly used for error reporting.
     * 
     * @return string
     */
    public function getName()
    {
        return "Twitter";
    }
}

