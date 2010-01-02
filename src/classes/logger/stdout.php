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

namespace TwIRCd\Logger;

/**
 * IRC Server
 * 
 * @package Core
 * @version $Revision$
 * @license http://www.gnu.org/licenses/gpl-3.0.txt GPL
 */
class StdOut extends \TwIRCd\Logger
{
    /**
     * Mapping of log severities to a textual representation.
     * 
     * @var array
     */
    protected $severityMapping = array(
        E_NOTICE  => 'Notice',
        E_WARNING => 'Warning',
        E_ERROR   => 'Error',
    );

    /**
     * Error reporting bitmask.
     *
     * Specifies the error levels, which are reported by this logger.
     * 
     * @var int
     */
    protected $errorReporting = 11;

    /**
     * Construct StdOut logger
     *
     * Optionally specify a bitmask for the error levels, which should be 
     * reported to STDOUT.
     * 
     * @param int $errorReporting 
     * @return void
     */
    public function __construct( $errorReporting = 11 /* E_NOTICE | E_WARNING | E_ERROR */ )
    {
        $this->errorReporting = (int) $errorReporting;
    }

    /**
     * Log message
     *
     * Log the given message. The emssage consists of a severity, using the 
     * standard PHP error constats E_NOTICE, E_WARNING and E_ERROR and a free 
     * text.
     * 
     * @param int $severity 
     * @param string $message 
     * @return void
     */
    public function log( $severity, $message )
    {
        if ( !( $severity & $this->errorReporting ) )
        {
            return;
        }

        fwrite( STDOUT, sprintf( "[%s] %s: %s\n",
            date( 'r' ),
            $this->severityMapping[$severity],
            trim( $message )
        ) );
    }
}

