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

namespace TwIRCd\Mapper;

/**
 * Ident mapper
 *
 * Creates configuration and microblogging clients based on provided user 
 * information or other environmental information.
 *
 * Instantiates the microblogging client based on the ident, the user provides. 
 * Defaults to a twitter client, if neither "twitter" or "identica" is 
 * specified.
 *
 * Always creates a XML configuration, which is stored in the CWD.
 *
 * @package Core
 * @version $Revision$
 * @license http://www.gnu.org/licenses/gpl-3.0.txt GPL
 */
class Ident extends \TwIRCd\Mapper
{
    /**
     * Initialize user account
     *
     * Initializes the microblogging client and the client configuration.
     * 
     * @param Irc\User $user 
     * @return void
     */
    public function initializeUserAccount( \TwIRCd\Irc\User $user )
    {
        $user->configuration = new \TwIRCd\Configuration\Xml( $user->nick . '_' . $user->ident . '.xml' );

        switch ( $user->ident )
        {
            case 'identica':
                $this->logger->log( E_NOTICE, "Instantiating identica client." );
                $user->client = new \TwIRCd\Client\Identica( $this->logger, $user->configuration );

            case 'twitter':
            default:
                $this->logger->log( E_NOTICE, "Instantiating twitter client." );
                $user->client = new \TwIRCd\Client\Twitter( $this->logger, $user->configuration );
        }

        $user->client->setCredentials( $user->nick, $user->password );
    }
}

