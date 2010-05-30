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
     * Search base URL
     * 
     * @var string
     */
    protected $searchBaseUrl = 'search.twitter.com';

    /**
     * Cache dir for twitter avatars.
     * 
     * @var string
     */
    protected $cacheDir;

    public function __construct( \TwIRCd\Logger $logger, \TwIRCd\Configuration $config )
    {
        parent::__construct( $logger, $config );
        $this->cacheDir = __DIR__ . '/../../../var/cache';
    }

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
     * @return array
     */
    public function getSearchResults( $channel, $count = 10 )
    {
        $searches   = $this->configuration->getSearches();
        $searchTerm = $searches[$channel];

        $this->logger->log( E_NOTICE, "Executing search for channel $channel: $searchTerm" );
        $since = $this->configuration->getLastUpdate( $type = 'search-' . $channel );

        $parameters = array(
            'count' => $count,
            'q'     => $searchTerm,
        );
        if ( $since !== null )
        {
            $parameters['since_id'] = $since;
        }

        $data = $this->httpRequest( 'GET', '/search.json', $parameters, "http://{$this->searchBaseUrl}" );

        $messages = array();
        if ( !count( $data ) || !is_array( $data ) || !count( $data['results'] ) )
        {
            return array();
        }

        $results = array_reverse( $data['results'] );
        foreach( $results as $message )
        {
            $messages[] = new Message(
                (string) $message['id'],
                $message['from_user'] . '!' . $message['from_user'] . '@twitter.com',
                $channel,
                $this->unfoldUrls( html_entity_decode( $message['text'] ) )
            );
        }

        $this->configuration->setLastUpdate( $type, $data['max_id'] );
        return $messages;
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
        $this->httpRequest(
            'POST',
            '/statuses/update.json',
            array(
                'status' => $this->shortenMessage( $string ),
            )
        );
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
        $this->httpRequest(
            'POST',
            '/direct_messages/new.json',
            array(
                'screen_name' => $user,
                'text'        => $this->shortenMessage( $string ),
            )
        );
    }

    /**
     * Shorten message
     *
     * Shortens a message by replacing URLs with tiny URLs, if it is too long, 
     * and throws an exception if the message couldn't be shortened 
     * sufficeiently.
     *
     * Returns the shortened message on success.
     * 
     * @param string $string 
     * @param int $length 
     * @return string
     */
    protected function shortenMessage( $string, $length = 140 )
    {
        // Try to shorten all included URLs, if message is too long otherwise
        if ( iconv_strlen( $string, 'UTF-8' ) > 140 )
        {
            $string = $this->shortenUrls( $string );
        }

        // Skip messages, which aree too long for twitter, and inform the user
        if ( iconv_strlen( $string, 'UTF-8' ) > 140 )
        {
            throw new \TwIRCd\LengthException(
                sprintf( "Skipping too long message (%d characters), overlapping part: '%s'\n",
                    iconv_strlen( $string, 'UTF-8' ),
                    iconv_substr( trim( $string ), 140, 100, 'UTF-8' )
                )
            );
        }

        return $string;
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

        $cursor = "-1";
        $friends = array();
        do {
            $json = $this->httpRequest( 'GET', '/statuses/friends.json', array( 'cursor' => $cursor ) );

            foreach( $json['users'] as $entry )
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

                if ( isset( $entry['profile_image_url'] ) )
                {
                    $friend->imgUrl = $entry['profile_image_url'];
                }
            }

            $cursor = isset( $json['next_cursor'] ) ? $json['next_cursor'] : false;
        } while ( $cursor );

        return $friends;
    }

    /**
     * Follow a user
     *
     * Send a follower request to the given user.
     * 
     * @param string $user
     * @return array
     */
    public function followUser( $user )
    {
        $this->logger->log( E_NOTICE, "Try to add user {$user} to friend list." );
        $this->httpRequest(
            'POST',
            '/friendships/create.json',
            array(
                'screen_name' => $user,
            )
        );
    }

    /**
     * Unfollow a user
     *
     * Unfollow the given user / remove it from the friends list.
     * 
     * @param string $user
     * @return array
     */
    public function unfollowUser( $user )
    {
        $this->logger->log( E_NOTICE, "Unfollow user {$user}." );
        $this->httpRequest(
            'POST',
            '/friendships/destroy.json',
            array(
                'screen_name' => $user,
            )
        );
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
                $this->generateAvatar( $message[$user]['screen_name'] ) . $this->unfoldUrls( html_entity_decode( $message['text'] ) )
            );
        }

        $this->configuration->setLastUpdate( $type, $lastId );
        return $messages;
    }

    /**
     * Returns an IRC avatar for the given $user.
     *
     * @TODO Make clean!!!
     */
    protected function generateAvatar( $user )
    {
        if ( $this->configuration->getValue( 'avatar', 'false' ) !== 'true' )
        {
            return '';
        }

        $this->logger->log( E_NOTICE, "Trying to generate avatar for user $user" );

        $data = $this->httpRequest( 'GET', '/users/show.json', array( 'screen_name' => $user ) );

        $url = $data['profile_image_url'];
        $file = $this->cacheDir . '/' . str_replace( '/', '_', parse_url( $url, PHP_URL_PATH ) );

        if ( !file_exists( $file ) )
        {
            file_put_contents( $file, file_get_contents( $url ) );
            $this->logger->log( E_NOTICE, "Fetched avatar to $file." );
        }

        $this->logger->log( E_NOTICE, "Generating IRC avatar for $user." );

        $ircImg = shell_exec(
            sprintf(
                'img2txt -f irc --height %s %s',
                escapeshellarg( $this->configuration->getValue( 'avatarHeight', '7' ) ),
                escapeshellarg( $file )
            )
        );

        if ( $ircImg === null )
        {
            $this->logger->log( E_WARNING, "Execution of img2txt returned null. Maybe caca-utils is not installed?" );
        }
        else
        {
            $this->logger->log( E_NOTICE, "Generated IRC avatar successfully." );
        }

        return $ircImg;
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
    protected function httpRequest( $method, $path, array $data = array(), $baseUrl = null )
    {
        $password = '';
        $url      = ( ( $baseUrl === null ) ? 'http://' . urlencode( $this->user ) . ':' . ( $password = urlencode( $this->password ) ) . '@' . $this->baseUrl : $baseUrl ) . $path;

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
            throw new \TwIRCd\ConnectionException( 'Could not connect to service.' );
        }

        // We need to fetch the body first, otherwise the headers are not 
        // available, when using --with-curl-wrappers
        $body    = $this->getResponseBody( $fp );
        $headers = $this->getHttpHeaders( $fp );
        fclose( $fp );

        // This check is not correct in terms of general HTTP handling, but 
        // should sufficient for twitter.
        if ( ( (int) $headers['status'] ) !== 200 )
        {
            throw new \TwIRCd\ConnectionException( "Response error recieved from twitter: " . $headers['status'] );
        }

        $this->updateRateLimit( $headers );
        $data = json_decode( $body, true );
        if ( isset( $data['error'] ) )
        {
            // On error, exit with error code
            throw new \TwIRCd\ConnectionException( $data['error'] );
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
        $percentTime       = $remainingTime / 3600;
        $percentRequests   = $remainingRequests / $requestsPerHour;

        // The additional factor of 1.1 is used to ensure, that we really do 
        // not touch the rate limit.
        $this->queueFactor = max( 1, ( $percentTime / $percentRequests ) * 1.1 );

        $this->logger->log( E_NOTICE, "Set queue factor to ( $remainingTime / 3600 ) / ( $remainingRequests / $requestsPerHour ) = ( $percentTime / $percentRequests ) = {$this->queueFactor}." );
    }
}

