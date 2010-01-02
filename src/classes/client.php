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
 * Abstract microblogging client base class
 */
abstract class iiClient
{
    /**
     * Storage class, which knows about the last update state
     * 
     * @var iiStorage
     */
    protected $storage;

    /**
     * User credentials
     * 
     * @var iiCredentials
     */
    protected $credentials;

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
     * Create microblogging client from user credentials
     * 
     * @param iiCredentials $credentials 
     * @return void
     */
    public function __construct( iiCredentials $credentials, iiStorage $storage )
    {
        $this->credentials = $credentials;
        $this->storage     = $storage;
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
     * Receive new messages
     *
     * Receive new messages from microblogging service.
     *
     * Returns an array of iiMessage objects.
     *
     * @return array
     */
    abstract public function getNewMessages();

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
    abstract public function sendMessage( $string );

    /**
     * Get client name
     *
     * Get a name of the client, which is mainly used for error reporting.
     * 
     * @return string
     */
    abstract public function getName();
}

