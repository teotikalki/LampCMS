<?php
/**
 *
 * License, TERMS and CONDITIONS
 *
 * This software is lisensed under the GNU LESSER GENERAL PUBLIC LICENSE (LGPL) version 3
 * Please read the license here : http://www.gnu.org/licenses/lgpl-3.0.txt
 *
 *  Redistribution and use in source and binary forms, with or without
 *  modification, are permitted provided that the following conditions are met:
 * 1. Redistributions of source code must retain the above copyright
 *    notice, this list of conditions and the following disclaimer.
 * 2. Redistributions in binary form must reproduce the above copyright
 *    notice, this list of conditions and the following disclaimer in the
 *    documentation and/or other materials provided with the distribution.
 * 3. The name of the author may not be used to endorse or promote products
 *    derived from this software without specific prior written permission.
 *
 * ATTRIBUTION REQUIRED
 * 4. All web pages generated by the use of this software, or at least
 * 	  the page that lists the recent questions (usually home page) must include
 *    a link to the http://www.lampcms.com and text of the link must indicate that
 *    the website's Questions/Answers functionality is powered by lampcms.com
 *    An example of acceptable link would be "Powered by <a href="http://www.lampcms.com">LampCMS</a>"
 *    The location of the link is not important, it can be in the footer of the page
 *    but it must not be hidden by style attibutes
 *
 * THIS SOFTWARE IS PROVIDED BY THE AUTHOR "AS IS" AND ANY EXPRESS OR IMPLIED
 * WARRANTIES, INCLUDING, BUT NOT LIMITED TO, THE IMPLIED WARRANTIES OF
 * MERCHANTABILITY AND FITNESS FOR A PARTICULAR PURPOSE ARE DISCLAIMED.
 * IN NO EVENT SHALL THE FREEBSD PROJECT OR CONTRIBUTORS BE LIABLE FOR ANY
 * DIRECT, INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY, OR CONSEQUENTIAL DAMAGES
 * (INCLUDING, BUT NOT LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS OR SERVICES;
 * LOSS OF USE, DATA, OR PROFITS; OR BUSINESS INTERRUPTION) HOWEVER CAUSED AND
 * ON ANY THEORY OF LIABILITY, WHETHER IN CONTRACT, STRICT LIABILITY, OR TORT
 * (INCLUDING NEGLIGENCE OR OTHERWISE) ARISING IN ANY WAY OUT OF THE USE OF
 * THIS SOFTWARE, EVEN IF ADVISED OF THE POSSIBILITY OF SUCH DAMAGE.
 *
 * This product includes GeoLite data created by MaxMind,
 *  available from http://www.maxmind.com/
 *
 *
 * @author     Dmitri Snytkine <cms@lampcms.com>
 * @copyright  2005-2011 (or current year) ExamNotes.net inc.
 * @license    http://www.gnu.org/licenses/lgpl-3.0.txt GNU LESSER GENERAL PUBLIC LICENSE (LGPL) version 3
 * @link       http://www.lampcms.com   Lampcms.com project
 * @version    Release: @package_version@
 *
 *
 */


namespace Lampcms;

/**
 * This class is responsible
 * for authenticating user during
 * the logging in to site
 * as well as during authenticating
 * from external programs like from
 * the nntp server or email server, etc.
 */
class UserAuth extends LampcmsObject
{

	public function __construct(Registry $oRegistry){
		$this->oRegistry = $oRegistry;
	}

	/**
	 * Check the username and password
	 * to make sure they are in valid format
	 * then actually checks username/password
	 * against values in database
	 *
	 * @return object of type User
	 *
	 * @param string $sUsername username OR
	 * email address entered in login form
	 *
	 * @param string $sPassword password entered in login form
	 */
	public function preCheckLogin($sUsername, $sPassword)
	{
		$this->checkMultipleLoginErrors($sUsername);
		$this->checkForBannedIP();
		if (false === Validate::enforcePwd($sPassword)) {
			d('failed to validate password');
			$this->logLoginError($sUsername, $sPassword);

			/**
			 * @todo
			 * translate the string
			 */
			throw new WrongPasswordException('Wrong password');
		}

		/**
		 * If logging in with email address
		 * then try to find user by email
		 *
		 * @todo
		 * Find user by email somehow
		 */
		if (false !== filter_var($sUsername, FILTER_VALIDATE_EMAIL)){
			$this->byEmail = true;
			$aEmail = $this->oRegistry->Mongo->getCollection('EMAILS')
			->findOne(array('_id' => $sUsername));

			if(empty($aEmail)){
				
				throw new WrongUserException('User with this email address not found');
			}

			d('$aEmail: '.print_r($aEmail, 1));
			$aResult = $this->oRegistry->Mongo->getCollection('USERS')
			->findOne(array('_id' => $aEmail['i_uid']));
			d('$aResult', print_r($aResult, 1));
			if(empty($aResult)){
				
				throw new WrongUserException('User not found');
			}

			$oUser = User::factory($this->oRegistry, $aResult);
		} else {

			if (false === Validate::username($sUsername)) {
				$this->logLoginError($sUsername, $sPassword, false);

				/**
				 * @todo
				 * Translate string
				 */
				throw new WrongUserException('Wrong user');
			}

			$oUser = $this->getUser($sUsername, $sPassword);
		}


		if (false === $this->comparePasswords($sPassword, $oUser['pwd'])) {
			d('failed to compare password');
			$this->logLoginError($sUsername, $sPassword);
			/**
			 * @todo
			 * translate string
			 */
			throw new WrongPasswordException('Wrong password');

		}

		return $oUser;
	}


	/**
	 * Get arr of UserInfo from
	 * cache
	 * @return array array of user data
	 *
	 * @param string $sUsername username to look for
	 *
	 * @todo allow user to also login by email address
	 * Must detect email address and then user uemail_ key instead
	 *
	 * @throws LampcmsLoginException
	 * in case user does not exist
	 */
	protected function getUser($sUsername, $sPassword)
	{
		d('$sUsername: '.$sUsername);
		/**
		 * @todo
		 * Not sure how to handle case sensitivity of username
		 * It should be case insensitive but it must
		 * be stored in USERS in same case as entered by user
		 * so... the best thing is probably to store it twice:
		 * as login and login_lc
		 * and index on login_lc
		 * @var unknown_type
		 */
		$arrResult = $this->oRegistry->Mongo->getCollection('USERS')
		->findOne(array('username_lc' => strtolower($sUsername)));

		d('$arrResult: '.print_r($arrResult, true));

		if ( empty ($arrResult)) {
			$this->logLoginError($sUsername, $sPassword, false);

			/**
			 * @todo
			 * Translate string
			 */
			throw new WrongUserException('Wrong user');

		}

		return User::factory($this->oRegistry, $arrResult);
	}


	/**
	 * Method to check login username and password
	 * and to set some values on _SESSION upon successful login
	 *
	 * @param string $sUsername username
	 *
	 * @param string $sPassword user password
	 *
	 * @return object of type User
	 *
	 * @throws LampcmsLoginException in case
	 * some other object cancells the 'onBeforeLogin'
	 * notification
	 */
	public function validateLogin($sUsername, $sPassword)
	{

		return $this->preCheckLogin($sUsername, $sPassword);

	}


	/**
	 * If there has been 5 incorrect login attempts
	 * for this user name in the last 6 minutes, then
	 * make user wait 5 minutes since the latest attempt
	 * before he can try again.
	 *
	 * @param $strUsername
	 *
	 * @return bool true on success
	 *
	 * @throws LampcmsMultiLoginException in case
	 * multiple login error detected form this $sUsername
	 */
	protected function checkMultipleLoginErrors($sUsername)
	{
		d('cp');

		$aLockParams = $this->oRegistry->Ini->getSection('LOGIN_ERROR_LOCK');
		d('$aLockParams: '.print_r($aLockParams, 1));
		/**
		 * If LOGIN_ERROR_LOCK was not set
		 * in SETTINGS or was set to false
		 * or if max_errors in that array
		 * is set to 0 then we don't do
		 * the multiple errors check
		 */
		if ( empty($aLockParams) || !is_array($aLockParams) || empty ($aLockParams['max_errors'])) {
			d('No settings for LOGIN_ERROR_LOCK');

			return true;
		}

		$now = time();
		$interval = ($now - $aLockParams['interval']);
		$wait = $aLockParams['wait'];

		$cur = $this->oRegistry->Mongo->getCollection('LOGIN_ERROR')
		->find(array('usr_lc' => strtolower($sUsername), 'i_ts' => array('$gt' => $interval)))
		->sort(array('i_ts' => -1));

		d('$cur: '.gettype($cur).' found count: '.$cur->count());

		if ($cur->count() > (int)$aLockParams['max_errors']) {
			$a1 = $cur->getNext();
			d('a1: '.print_r($a1, 1));
			$lastAttempt = ($now - $a1['i_ts']);

			d('$lastAttempt: '.$lastAttempt);
			if ($lastAttempt < $wait) {
				$remaining = ceil( ($wait - $lastAttempt) / 60);
				/**
				 * @todo
				 * Translate string
				 */
				$strMessage = 'Multiple incorrect login attempts. Please wait %d minute%s before trying again';
				$strSuff = ($remaining === 1) ? '' : 's';
				$err = sprintf($strMessage, $remaining, $strSuff);
				d('err: '.$err);

				throw new MultiLoginException($err);
			}
		}

		return true;

	}



	/**
	 * Checks that request did not
	 * come from ip address that was previously
	 * banned for hack attempts
	 *
	 * @return object $this
	 *
	 * @throws LampcmsCookieAuthException
	 * if request came from ip address that
	 * was banned for attempting to hack
	 * login by cookie
	 */
	protected function checkForBannedIP()
	{
		$ip = Request::getIP();

		$timediff = (time() - 600); // 10 minutes

		$cur = $this->oRegistry->Mongo->getCollection('LOGIN_ERROR')
		->find(array('ip' => $ip, 'i_ts' => array('$gt' => $timediff)))
		->limit(7);

		if($cur && (5 < $cur->count(true)) ){
			e('multiple bad logins from the ip: '.$ip);
				
			throw new MultiLoginException('Attempting to login from IP address that is temporarily blocked due to previous login failures');
		}

		return $this;
	}


	/**
	 * Add data to LOGIN_ERROR table
	 * data includes username, password, geoip, useragent, timestamp
	 * of bad login attempt
	 *
	 * @param string $username
	 * @param string $pwd
	 * @param bool $username_exists
	 * @param string $strIp
	 * @param bool $bByCookie login was done using
	 * cookies uid and sid
	 *
	 * @return bool false
	 * by returning false we can use the result of this method
	 * as a return of fnLogin
	 */
	protected function logLoginError($username, $pwd = '', $username_exists = true, $strIp = null,
	$login_type = 'www')
	{
		if (!$username_exists) {
			d('NO User with nick '.$username);
		} else {
			d('Error: wrong password for '.$username.' and password: '.$pwd);
		}

		$login_type = (true === $login_type) ? 'cookie' : $login_type;
		$ip = (null !== $strIp) ? $strIp : Request::getIP();
		$username_lc = strtolower($username);
		$usr_exists = ($username_exists) ? 'Y' : 'N';
		$i_ts = time();
		$time = date('r');
		$ua = Request::getUserAgent();
		$aData = compact('usr_lc',
						 'pwd',
                         'usr_exists',
                         'ua',
                         'i_ts',
                         'login_type',
						 'time');

		d('aData: '.print_r($aData, 1));
		/**
		 * Insure these 2 indexes
		 * the index for username will be automatically
		 * ensured in saveResourceLocation()
		 *
		 */
		$coll = $this->oRegistry->Mongo->getCollection('LOGIN_ERROR');
		$indexed1 = $coll->ensureIndex(array('usr_lc' => 1));
		$indexed1 = $coll->ensureIndex(array('i_ts' => 1));
		$indexed2 = $coll->ensureIndex(array('ip' => 1));

		if ('cookie' === $login_type) {
			$this->oRegistry->Dispatcher->post($this, 'onSidHack');
		} elseif ('switch' === $login_type) {
			$this->oRegistry->Dispatcher->post($this, 'onSwitchHack');
		}

		return false;
	}


	/**
	 * Compare supplied password against
	 * the hashed password from database
	 *
	 * @return boolean true if passwords are the same, false otherwise
	 *
	 * @param string $supplied
	 * @param string $stored
	 */
	protected function comparePasswords($supplied, $stored)
	{
		/**
		 * Very important to trim,
		 * some browsers pass extra white space sometimes!
		 */
		$hashed = String::hashPassword(trim($supplied));
		d('$supplied: '.$supplied.' hashed: '.$hashed.' stored:' . $stored);


		return ($hashed === $stored);
	}

}
