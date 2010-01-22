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

require __DIR__ . '/classes/logger.php';
require __DIR__ . '/classes/logger/stdout.php';

require __DIR__ . '/classes/irc/message.php';
require __DIR__ . '/classes/irc/user.php';
require __DIR__ . '/classes/irc/server.php';

require __DIR__ . '/classes/client.php';
require __DIR__ . '/classes/client/friend.php';
require __DIR__ . '/classes/client/message.php';
require __DIR__ . '/classes/client/twitter.php';

require __DIR__ . '/classes/configuration.php';
require __DIR__ . '/classes/configuration/xml.php';

require __DIR__ . '/classes/mapper.php';
require __DIR__ . '/classes/mapper/ident.php';

require __DIR__ . '/classes/exception.php';
require __DIR__ . '/classes/server.php';

