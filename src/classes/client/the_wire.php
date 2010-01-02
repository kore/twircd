<?php
/**
 * This file is part of thewire2ii
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
 * TheWire microblogging client base class
 */
class iiTheWireClient extends iiClient
{
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
                                (?P<url>http://thewire\.ez\.no/[^\s]*?)
                            \4
                        (?(3)\))
                    (?(2)\])
                (?(1)>)
            (?# Ignore common punctuation after the URL)
        [.,?!]?(?:\s|$)
    )xm';

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
        while ( preg_match( self::URL_REGEX, $message, $match ) )
        {
            // Use internal error handling to handle XML errors manually.
            $oldXmlErrorHandling = libxml_use_internal_errors( true );
            libxml_clear_errors();
            $doc = new DOMDocument();
            $doc->loadHtml(
                file_get_contents(
                    str_replace( 'http://', 'http://' . $this->credentials->user . ':' . $this->credentials->password . '@', $match['url'] )
                )
            );
            libxml_clear_errors();
            libxml_use_internal_errors( $oldXmlErrorHandling );

            $xpath = new DOMXPath( $doc );
            $link = $xpath->query( '//div[@id="main"]/p/a[2]' )->item( 0 );

            $message = str_replace( $match['url'], trim( $link->textContent ), $message );
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
    public function getNewMessages()
    {
        $lastMessage = $this->storage->get( $key = 'thewire/' . $this->credentials->user );

        // Receive all new messages from RSS feed
        // Use internal error handling to handle XML errors manually.
        $oldXmlErrorHandling = libxml_use_internal_errors( true );
        libxml_clear_errors();
        $feed = @simplexml_load_file( 'http://' . $this->credentials->user . ':' . $this->credentials->password . '@thewire.ez.no/rss' );
        libxml_clear_errors();
        libxml_use_internal_errors( $oldXmlErrorHandling );

        // Check, that we got some valid data from feed.
        if ( !$feed || 
             !isset( $feed->channel ) ||
             !count( $feed->channel->item ) )
        {
            throw new iiClientException( 'Could not fetch data.' );
        }

        $messages = array();
        $newLast  = $lastMessage;
        foreach ( $feed->channel->item as $item )
        {
            $date = new DateTime( (string) $item->pubDate );
            if ( $date->getTimestamp() <= $lastMessage )
            {
                continue;
            }

            // Split up message
            if ( preg_match( '(^(?P<nick>[^:]+):\s*(?P<text>.*))s', (string) $item->title, $match ) )
            {
                $update = new iiMessage(
                    $date,
                    $match['nick'],
                    $this->unfoldUrls( trim( $match['text'] ) )
                );
            }
            else
            {
                // No valid entry found
                continue;
            }

            // Try to also fetch realname
            if ( preg_match( '(^(?P<name>[^:]+):)s', (string) $item->description, $match ) )
            {
                $update->realName = $match['name'];
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
        // Send using a plain HTTP Post request
        //
        // Silence error messages about connection failure or similar, those are
        // somehow expected with twitter.
        $fp = @fopen(
            $url = 'http://' . $this->credentials->user . ':' . $this->credentials->password . '@thewire.ez.no/add', 'r', false,
            stream_context_create(
                array(
                    'http' => array(
                        'method'        => 'POST',
                        'content'       => http_build_query( array(
                            'update'     => $string,
                            'add-update' => 'Go',
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

        // Read the return value
        $return = '';
        while ( !feof( $fp ) )
        {
            $return .= fread( $fp, 1024 );
        }

        fclose( $fp );
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
        return "TheWire";
    }
}

