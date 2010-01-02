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
 * Identi.ca microblogging client. Identi.ca can optionally be used using the
 * same API as Twitter, so we just need to change the base URLs.
 */
class iiIdenticaClient extends iiTwitterClient
{
    /**
     * Service base URL
     * 
     * @var string
     */
    protected $baseUrl = 'identi.ca/api';

    /**
     * Key identifying the service
     * 
     * @var string
     */
    protected $key = 'identica';

    /**
     * Get client name
     *
     * Get a name of the client, which is mainly used for error reporting.
     * 
     * @return string
     */
    public function getName()
    {
        return "Identi.ca";
    }
}

