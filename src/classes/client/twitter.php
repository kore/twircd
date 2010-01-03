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
 * Twitter microblogging client
 *
 * @package Core
 * @version $Revision$
 * @license http://www.gnu.org/licenses/gpl-3.0.txt GPL
 */
class Twitter extends \TwIRCd\Client
{
    /**
     * Service base URL
     * 
     * @var string
     */
    protected $baseUrl = 'twitter.com';

    /**
     * Receive new messages
     *
     * Receive new messages from the timeline microblogging service, since the 
     * last request, specified by a time stamp.
     *
     * Returns an array of message objects.
     *
     * Schould only be accessed indirectly through the getUpdates() method, 
     * which maintains a request queue to respect the rate limits of the 
     * microblogging service.
     *
     * @param int $since 
     * @return array
     */
    public function getTimeline( $since )
    {
        $this->logger->log( E_NOTICE, "Retrive friends timeline for user {$this->user}." );
        $data = $this->httpRequest( 'GET', '/statuses/friends_timeline.json', array(
            'since' => date( DATE_RFC822, $since ),
            'count' => 50,
        ) );

        $messages = array();
        if ( count( $data ) && is_array( $data ) )
        {
            $data = array_reverse( $data );
            foreach( $data as $message )
            {
                // Twitter some times still sends all messages, even only new
                // message since some date are requested, so we need to recheck
                // that manually.
                $date = new \DateTime( $message['created_at'] );
                if ( $date->getTimestamp() < $since )
                {
                    continue;
                }

                $messages[] = new Message(
                    $message['user']['screen_name'] . '!' . $message['user']['screen_name'] . '@twitter.com',
                    '&twitter',
                    $this->unfoldUrls( html_entity_decode( $message['text'] ) )
                );
            }
        }

        return $messages;
    }

    /**
     * Receive direct messages
     *
     * Receive new direct messages from the microblogging service, since the 
     * las request, specified as a time stamp.
     *
     * Returns an array of message objects.
     *
     * Schould only be accessed indirectly through the getUpdates() method, 
     * which maintains a request queue to respect the rate limits of the 
     * microblogging service.
     *
     * @param int $since 
     * @return array
     */
    public function getDirectMessages( $since )
    {
        // @todo: Implement
        return array();
    }

    /**
     * Receive search results
     *
     * Receive new search results from the microblogging service, since the 
     * last request, specified as a time stamp.
     *
     * Returns an array of message objects.
     *
     * Schould only be accessed indirectly through the getUpdates() method, 
     * which maintains a request queue to respect the rate limits of the 
     * microblogging service.
     *
     * @param int $since 
     * @param string $channel 
     * @param string $search 
     * @return array
     */
    public function getSearchResults( $since, $channel, $search )
    {
        // @todo: Implement
        return array();
    }

    /**
     * Update status
     *
     * Send given string as a message using the given microblogging service.
     * There might be some restrictions on the message, depending on the
     * service. Violating these an exception might be thrown.
     * 
     * @param string $string 
     * @return void
     */
    public function updateStatus( $string )
    {
        // Try to shorten all included URLs, if message is too long otherwise
        if ( strlen( $string ) > 140 )
        {
            $string = $this->shortenUrls( $string );
        }

        // Skip messages, which aree too long for twitter, and inform the user
        if ( strlen( $string ) > 140 )
        {
            throw new \Exception(
                sprintf( "Skipping too long message (%d characters), overlapping part: '%s'\n",
                    strlen( $string ),
                    substr( trim( $string ), 140 )
                )
            );
        }

        $this->httpRequest( 'POST', '/statuses/update.json', array( 'status' => $string ) );
    }

    /**
     * Send a direct message
     *
     * Send given string as a direct message to another user using the given 
     * microblogging service. There might be some restrictions on the message, 
     * depending on the service. Violating these an exception might be thrown.
     * 
     * @param string $user 
     * @param string $string 
     * @return void
     */
    public function sendDirectMessage( $user, $string )
    {
        // @todo: Implement
    }

    /**
     * Get friend list
     *
     * Get a list of all followers (friends) of the user.
     * 
     * @return array
     */
    public function getFriends()
    {
        $this->logger->log( E_NOTICE, "Retrive friend list for user {$this->user}." );
        $json = $this->httpRequest( 'GET', '/statuses/friends.json' );

        $friends = array();
        foreach( $json as $entry )
        {
            $friends[] = $friend = new Friend( $entry['screen_name'] );

            if ( isset( $entry['status'] ) &&
                 isset( $entry['status']['text'] ) )
            {
                $friend->status = $entry['status']['text'];
            }

            if ( isset( $entry['name'] ) )
            {
                $friend->status = $entry['name'];
            }
        }

        return $friends;
    }

    /**
     * Perform a HTTP request
     *
     * Performs a HTTP request, using the client environment, like the base 
     * path, the configured username and password.
     *
     * Appends the optional data, depending on the request method. Implements 
     * error handling for the twitter requests, and throws an exception for 
     * occured errors.
     *
     * Returns an array with the data provided by the service on success.
     * 
     * @param string $method 
     * @param string $path 
     * @param array $data 
     * @return array
     */
    protected function httpRequest( $method, $path, array $data = array() )
    {
        $url = 'http://' . urlencode( $this->user ) . ':' . urlencode( $this->password ) . '@' . $this->baseUrl . $path;

        // Append data to URL for GET requests
        if ( ( $method === 'GET' ) && 
             count( $data ) )
        {
            $url .= '?' . http_build_query( $data );
        }
    
        // Configure request options
        $options = array(
            'http' => array(
                'method'        => $method,
                'ignore_errors' => true,
            ),
        );

        // Append data to body for non-GET requests
        if ( ( $method !== 'GET' ) && 
             count( $data ) )
        {
            $options['http']['content'] = http_build_query( $data );
        }

        // Receive all new messages
        //
        // Silence error messages about connection failure or similar, those are
        // somehow expected with twitter.
        $this->logger->log( E_NOTICE, $url );
        $fp = @fopen( $url, 'r', false, stream_context_create( $options ) );

        if ( $fp === false )
        {
            throw new \Exception( 'Could not connect to service.' );
        }

        // Read all the returned stuff
        $data = '';
        while ( !feof( $fp ) )
        {
            $data .= fread( $fp, 1024 );
        }
        fclose( $fp );
        $data = json_decode( $data, true );

        if ( isset( $data['error'] ) )
        {
            // On error, exit with error code
            throw new \Exception( $data['error'] );
        }

        return $data;
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
    public function oldfetchSearch( $term )
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
    protected function oldfetchSearches()
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
}

