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
 * Abstract microblogging client base class
 *
 * @package Core
 * @version $Revision$
 * @license http://www.gnu.org/licenses/gpl-3.0.txt GPL
 */
abstract class Client
{
    /**
     * Hosts for which no URL shortening should happen.
     * 
     * @var array(string=>true)
     */
    protected $noShorteningHosts = array(
        '3.ly'        => true,
        'bit.ly'      => true,
        'imgur.com'   => true,
        'is.gd'       => true,
        'short.ie'    => true,
        'tinyurl.com' => true,
        'tr.im'       => true,
        'twitpic.com' => true,
        'u.nu'        => true,
        'url.ca'      => true,
        'url.ie'      => true,
    );

    /**
     * Factor to delay requests to the service
     *
     * The time span between the requests will be slowed down by this factor. 
     * The factor itself will be adapted, if the rate limits of the service 
     * were reached.
     * 
     * @var float
     */
    protected $queueFactor = 1;

    /**
     * Queue with all stacked requests to the microblogging service.
     * 
     * @var array
     */
    protected $queue = array();

    /**
     * Default update times for the reading requests to the microblogging 
     * service. They might be different in the actual run,because they are 
     * modified by the $queueFactor property.
     * 
     * @var array
     */
    protected $updateTimes = array(
        'getTimeline'       => 60,
        'getDirectMessages' => 60,
        'getSearchResults'  => 300,
    );

    /**
     * User name
     * 
     * @var string
     */
    protected $user;

    /**
     * Password
     * 
     * @var string
     */
    protected $password;

    /**
     * Logger
     * 
     * @var \TwIRCd\Logger
     */
    protected $logger;

    /**
     * Configuration object, storing the last update times / IDs
     * 
     * @var \TwIRCd\Configuration
     */
    protected $configuration;

    /**
     * Regular expression to "parse" URLs out of text. 
     */
    const URL_REGEX = '(
        (?:^|[\s,.!?])
            (?# Ignore matching braces around the URL)
                (<)?
                    (\[)?
                        (\()?
                            (?# Ignore quoting around the URL)
                            ([\'"]?)
                                (?# Actually match the URL)
                                (?P<match>
                                    (?P<url>[a-z]+://[^\s]*?) |
                                    (?:mailto:)?(?P<mail>[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,})
                                )
                            \4
                        (?(3)\))
                    (?(2)\])
                (?(1)>)
            (?# Ignore common punctuation after the URL)
        [.,?!]?(?:\s|$)
    )xm';

    /**
     * Construct client from logger
     * 
     * @param \TwIRCd\Logger $logger 
     * @param \TwIRCd\Configuration $configuration 
     * @return void
     */
    public function __construct( \TwIRCd\Logger $logger, \TwIRCd\Configuration $configuration )
    {
        $this->logger        = $logger;
        $this->configuration = $configuration;
    }

    /**
     * Set user credentials
     * 
     * @param string $user 
     * @param string $password 
     * @return void
     */
    public function setCredentials( $user, $password )
    {
        $this->user     = $user;
        $this->password = $password;
    }

    /**
     * Unfold URLs in message
     *
     * Unfold URLs used with tinyurl services to their original URLs. Return
     * resulting message.
     *
     * @param string $message 
     * @return string
     */
    protected function unfoldUrls( $message )
    {
        return $message;
    }

    /**
     * Shorten URLs in message
     *
     * Shorten all URLs in the message using the tinyurl service. Returns the
     * message with the shortened URLs included.
     *
     * @param string $message 
     * @return string
     */
    protected function shortenUrls( $message )
    {
        // Auto replace long URLs
        if ( preg_match_all( self::URL_REGEX, $message, $matches ) > 0 )
        {
            $urls = array_unique( $matches['url'] );
            foreach ( $urls as $url )
            {
                if ( isset( $this->noShorteningHosts[parse_url( $url, PHP_URL_HOST )] ) )
                {
                    continue;
                }
                
                $tinyUrl = trim(
                    file_get_contents(
                        'http://tinyurl.com/api-create.php?url=' . urlencode( $url )
                    )
                );
                $message = str_replace( $url, $tinyUrl, $message );
            }
        }

        return $message;
    }

    /**
     * Add call to queue
     *
     * Add a call to the microblogging backend to the queue. The supported call 
     * types are:
     *
     * - getTimeline
     * - getDirectMessages
     * - getSearchResults
     *
     * They all have different parameters, while the first is always the last 
     * call to the service.
     * 
     * @param string $type 
     * @param array $parameters 
     * @return void
     */
    public function queue( $type, array $parameters = array() )
    {
        $this->queue[] = array(
            'type'       => $type,
            'parameters' => $parameters,
            'scheduled'  => 0,
        );
    }

    /**
     * Receive updates
     *
     * Receive updates from searches, direct messages and the timeline. Only 
     * returns something, if the queue offers something to process. Can be 
     * called at any rate and ensures itself, that the services are not called 
     * too often.
     *
     * Returns an array of message objects.
     * 
     * @return array
     */
    public function getUpdates()
    {
        $current = time();
        foreach ( $this->queue as &$entry )
        {
            if ( $entry['scheduled'] < $current )
            {
                $result = call_user_func_array(
                    array( $this, $entry['type'] ),
                    $entry['parameters']
                );
                $entry['parameters'][0] = $current;
                $entry['scheduled']     = $current + $this->queueFactor * $this->updateTimes[$entry['type']];
                $this->logger->log( E_NOTICE, "Rescheduled item {$entry['type']} at " . date( 'r', $entry['scheduled'] ) . '.' );

                return $result;
            }
        }

        return array();
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
    abstract public function getTimeline();

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
    abstract public function getDirectMessages();

    /**
     * Receive search results
     *
     * Receive new search results from the microblogging service
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
    abstract public function getSearchResults( $channel, $search );

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
    abstract public function updateStatus( $string );

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
    abstract public function sendDirectMessage( $user, $string );

    /**
     * Get friend list
     *
     * Get a list of all followers (friends) of the user.
     * 
     * @return array
     */
    abstract public function getFriends();
}

