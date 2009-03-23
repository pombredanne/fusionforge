<?php
/*
 * Copyright (C) 2008 Alain Peyrat <aljeux@free.fr>
 * Copyright (C) 2009 Alain Peyrat, Alcatel-Lucent
 *
 * This file is part of FusionForge.
 *
 * FusionForge is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published
 * by the Free Software Foundation; either version 2 of the License,
 * or (at your option) any later version.
 * 
 * FusionForge is distributed in the hope that it will be useful, but
 * WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the GNU
 * General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with FusionForge; if not, write to the Free Software
 * Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA 02111-1307
 * USA
 */

/*
 * Standard Alcatel-Lucent disclaimer for contributing to open source
 *
 * "The test suite ("Contribution") has not been tested and/or
 * validated for release as or in products, combinations with products or
 * other commercial use. Any use of the Contribution is entirely made at
 * the user's own responsibility and the user can not rely on any features,
 * functionalities or performances Alcatel-Lucent has attributed to the
 * Contribution.
 *
 * THE CONTRIBUTION BY ALCATEL-LUCENT IS PROVIDED AS IS, WITHOUT WARRANTY
 * OF ANY KIND, EXPRESS OR IMPLIED, INCLUDING BUT NOT LIMITED TO THE
 * WARRANTIES OF MERCHANTABILITY, FITNESS FOR A PARTICULAR PURPOSE, COMPLIANCE,
 * NON-INTERFERENCE AND/OR INTERWORKING WITH THE SOFTWARE TO WHICH THE
 * CONTRIBUTION HAS BEEN MADE, TITLE AND NON-INFRINGEMENT. IN NO EVENT SHALL
 * ALCATEL-LUCENT BE LIABLE FOR ANY DAMAGES OR OTHER LIABLITY, WHETHER IN
 * CONTRACT, TORT OR OTHERWISE, ARISING FROM, OUT OF OR IN CONNECTION WITH THE
 * CONTRIBUTION OR THE USE OR OTHER DEALINGS IN THE CONTRIBUTION, WHETHER
 * TOGETHER WITH THE SOFTWARE TO WHICH THE CONTRIBUTION RELATES OR ON A STAND
 * ALONE BASIS."
 */

require_once 'config.php';
require_once 'Testing/SeleniumGforge.php';
require_once 'PHPUnit/Framework/TestCase.php';

class Top extends PHPUnit_Framework_TestCase
{
    function setUp()
    {
    	global $url;
    	
        $this->verificationErrors = array();
		$this->selenium = new Testing_SeleniumGforge($this, "*firefox", URL, SELENIUM_RC_HOST);
        $result = $this->selenium->start();
    }

    function tearDown()
    {
        $this->selenium->stop();
    }

    function testWalkInTop()
    {
    	$this->selenium->open( BASE );

		// Test that from the main page we access the most active this week.    
	    $this->selenium->click("link=[ More ]");
    	$this->selenium->waitForPageToLoad("30000");
    	$this->assertTrue($this->selenium->isTextPresent("Most Active This Week"));

    	// Test that we can return back to all the tops.
    	$this->selenium->click("link=[View Other Top Categories]");
    	$this->selenium->waitForPageToLoad("30000");
    	$this->assertTrue($this->selenium->isTextPresent("We track many project usage statistics"));

    	// Test that we can go the view the most active all time.
    	$this->selenium->click("link=Most Active All Time");
    	$this->selenium->waitForPageToLoad("30000");
    	$this->assertTrue($this->selenium->isTextPresent("Most Active All Time"));

    	// Return back to tops.
    	$this->selenium->click("link=[View Other Top Categories]");
    	$this->selenium->waitForPageToLoad("30000");
    	$this->selenium->click("link=Top Downloads");
    	$this->selenium->waitForPageToLoad("30000");
    	$this->assertTrue($this->selenium->isTextPresent("Rank"));
    }
}
?>
