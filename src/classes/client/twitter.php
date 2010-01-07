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
     * Receive new messages from the timeline microblogging service.
     *
     * Returns an array of message objects.
     *
     * Schould only be accessed indirectly through the getUpdates() method, 
     * which maintains a request queue to respect the rate limits of the 
     * microblogging service.
     *
     * @return array
     */
    public function getTimeline()
    {
        return $this->getMessages( '/statuses/home_timeline', 'friends_timeline' );
    }

    /**
     * Receive mentions
     *
     * Receive mentions by other users from the microblogging service.
     *
     * Returns an array of message objects.
     *
     * Schould only be accessed indirectly through the getUpdates() method, 
     * which maintains a request queue to respect the rate limits of the 
     * microblogging service.
     *
     * @return array
     */
    public function getMentions()
    {
        return $this->getMessages( '/statuses/mentions', 'mentions' );
    }

    /**
     * Receive direct messages
     *
     * Receive new direct messages from the microblogging service.
     *
     * Returns an array of message objects.
     *
     * Schould only be accessed indirectly through the getUpdates() method, 
     * which maintains a request queue to respect the rate limits of the 
     * microblogging service.
     *
     * @return array
     */
    public function getDirectMessages()
    {
        $messages = $this->getMessages( '/direct_messages', 'direct_messages' );

        // Redirect messages, so that they will be recieved in a query
        foreach ( $messages as $message )
        {
            $message->to = $this->user;
        }
        return $messages;
    }

    /**
     * Receive search results
     *
     * Receive new search results from the microblogging service.
     *
     * Returns an array of message objects.
     *
     * Schould only be accessed indirectly through the getUpdates() method, 
     * which maintains a request queue to respect the rate limits of the 
     * microblogging service.
     *
     * @param string $channel 
     * @param string $search 
     * @return array
     */
    public function getSearchResults( $channel, $search )
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
            $friends[$entry['screen_name']] = $friend = new Friend( $entry['screen_name'] );

            if ( isset( $entry['status'] ) &&
                 isset( $entry['status']['text'] ) )
            {
                $friend->status = $entry['status']['text'];
            }

            if ( isset( $entry['name'] ) )
            {
                $friend->realName = $entry['name'];
            }
        }

        return $friends;
    }

    /**
     * Receive a set of messages from service
     *
     * Receives a set of messages of the specified type (required for the 
     * associated configuration key), from the specified path.
     *
     * Returns an array of Message objects.
     * 
     * @param string $path 
     * @param string $type 
     * @param int $count
     * @return array
     */
    protected function getMessages ( $path, $type, $count = 20 )
    {
        $since = $this->configuration->getLastUpdate( $type );

        $parameters = array( 'count' => $count );
        if ( $since !== null )
        {
            $parameters['since_id'] = $since;
        }

        $this->logger->log( E_NOTICE, "Retrive $type messages for user {$this->user}." );
        $data = $this->httpRequest( 'GET', "$path.json", $parameters );

        $messages = array();
        if ( !count( $data ) || !is_array( $data ) )
        {
            return array();
        }

        $data = array_reverse( $data );
        foreach( $data as $message )
        {
            // The user key is different in direct messages and timeline 
            // messages
            $user = isset( $message['user'] ) ? 'user' : 'sender';

            $messages[] = new Message(
                $lastId = (string) $message['id'],
                $message[$user]['screen_name'] . '!' . $message[$user]['screen_name'] . '@twitter.com',
                '&twitter',
                $this->unfoldUrls( html_entity_decode( $message['text'] ) )
            );
        }

        $this->configuration->setLastUpdate( $type, $lastId );
        return $messages;
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
        $url = 'http://' . urlencode( $this->user ) . ':' . ( $password = urlencode( $this->password ) ) . '@' . $this->baseUrl . $path;

        // Append data to URL for GET requests
        if ( ( $method === 'GET' ) && count( $data ) )
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
        if ( ( $method !== 'GET' ) && count( $data ) )
        {
            $options['http']['content'] = http_build_query( $data );
        }

        // Receive all new messages
        //
        // Silence error messages about connection failure or similar, those are
        // somehow expected with twitter.
        $this->logger->log( E_NOTICE, 'Request URL: ' . str_replace( $password, '***', $url ) );
        $fp = @fopen( $url, 'r', false, stream_context_create( $options ) );

        if ( $fp === false )
        {
            throw new \Exception( 'Could not connect to service.' );
        }

        $headers = $this->getHttpHeaders( $fp );
        $body    = $this->getResponseBody( $fp );
        fclose( $fp );

        // This check is not correct in terms of general HTTP handling, but 
        // should sufficient for twitter.
        if ( ( (int) $headers['status'] ) !== 200 )
        {
            throw new \Exception( "Response error recieved from twitter: " . $headers['status'] );
        }

        $this->updateRateLimit( $headers );
        $data = json_decode( $body, true );
        if ( isset( $data['error'] ) )
        {
            // On error, exit with error code
            throw new \Exception( $data['error'] );
        }

        return $data;
    }

    /**
     * Get HTTP headers from resource
     *
     * Return an array with the HTTP headers from the given resource. The 
     * resource should be a fopen()'ed HTTP stream wrapper.
     * 
     * @param resource $fp 
     * @return array
     */
    protected function getHttpHeaders( $fp )
    {
        // Extract response headers
        $metaData   = stream_get_meta_data( $fp );
        // The array content depends on whether PHP is compiled with 
        // --with-curl-wrappers or not. To handle both variants, this check is 
        // required.
        $rawHeaders = isset( $metaData['wrapper_data']['headers'] ) ? $metaData['wrapper_data']['headers'] : $metaData['wrapper_data'];
        $headers    = array();
        foreach ( $rawHeaders as $lineContent )
        {
            // Extract header values
            if ( preg_match( '(^HTTP/(?P<version>\d+\.\d+)\s+(?P<status>\d+))S', $lineContent, $match ) )
            {
                $headers['version'] = $match['version'];
                $headers['status']  = (int) $match['status'];
            }
            else
            {
                list( $key, $value ) = explode( ':', $lineContent, 2 );
                $headers[strtolower( $key )] = ltrim( $value );
            }
        }

        return $headers;
    }

    /**
     * Get HTTP response body from resource
     *
     * Return an array with the HTTP response body from the given resource. The 
     * resource should be a fopen()'ed HTTP stream wrapper.
     * 
     * @param resource $fp 
     * @return string
     */
    protected function getResponseBody( $fp )
    {
        // Read all the returned stuff
        $data = '';
        while ( !feof( $fp ) )
        {
            $data .= fread( $fp, 1024 );
        }

        return $data;
    }

    /**
     * Update rate limit
     *
     * Updates the rate limit factor, depending on the response twitter sent. 
     * Each response from twitter tells which amount of requests is still 
     * available in the current time slot, so that we can guessimate a new rate 
     * limit factor.
     *
     * This method sets the $queueFactor property, which influences the request 
     * times to the Twitter API.
     * 
     * @param array $httpHeaders 
     * @return void
     */
    protected function updateRateLimit( array $httpHeaders )
    {
        if ( !isset( $httpHeaders['x-ratelimit-remaining'] ) )
        {
            // Not all responses must contain rate limit information.
            return;
        }

        $remainingTime     = $httpHeaders['x-ratelimit-reset'] - time();
        $remainingRequests = $httpHeaders['x-ratelimit-remaining'];
        $requestsPerHour   = $httpHeaders['x-ratelimit-limit'];
        $percentTime       = 1 - $remainingTime / 3600;
        $percentRequests   = 1 - $remainingRequests / $requestsPerHour;

        // The additional factor of 1.1 is used to ensure, that we really do 
        // not touch the rate limit.
        $this->queueFactor = ( $percentTime / $percentRequests ) * 1.1;

        $this->logger->log( E_NOTICE, "Set queue factor to {$this->queueFactor}." );
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

