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

namespace Lampcms\Controllers;

use \Lampcms\WebPage;
use \Lampcms\Request;
use \Lampcms\String;

/**
 * Class responsible for
 * displaying the reset password
 * form, processing the form,
 * generating a new ramdom password
 * for user
 * and emailing it to user
 */
class Resetpwd extends WebPage
{
	const TPL_SUCCESS = 'New password was just sent to your email %s';

	const SUBJECT = 'Your %1$s login information';

	const EMAIL_BODY = 'This email contains your login information for %1$s

Your login: %2$s
Your password is: %3$s

You are advised to store the above information in a safe place so that you
do not face any inconvenience in future.

You can also change your password after you log in. 
	';

	protected $aRequired = array('uid', 'r');

	protected $username;

	protected $email;

	/**
	 * Newly generated password
	 * @var string
	 */
	protected $newPwd;

	protected function main()
	{

		d('$this->newPwd: '.$this->newPwd);

		$this->checkHacks()
		->validateCode()
		->generatePassword()
		->savePassword()
		->emailPwd();

		$this->aPageVars['title'] = 'Password reset';
		$this->aPageVars['body'] = '<div class="frm1">'.sprintf(self::TPL_SUCCESS, $this->email).'</div>';

	}


	protected function generatePassword(){
		$this->newPwd = String::makePasswd();

		return $this;
	}


	/**
	 *
	 * Update USERS collection with the
	 * new value of salted password
	 *
	 * @param string $pwd
	 * @param int $uid
	 *
	 * @return object $this
	 */
	protected function savePassword(){
		d('$this->newPwd: '.$this->newPwd);

		$salted = String::hashPassword($this->newPwd);
		$newdata = array('$set' => array("pwd" => $salted));

		$this->oRegistry->Mongo->getCollection('USERS')
		->update(array('_id' => (int)$this->oRequest['uid']), $newdata);

		return $this;
	}


	/**
	 * Checks that supplied password reset code
	 * is valid and matches the supplied uid
	 * and is not older than 24 hours
	 *
	 * Up to 10 results will be selected
	 * and each one will be tested untill
	 * a positive match is found.
	 *
	 * This is so that
	 * if a user requested a password reset link
	 * several times
	 * (maybe forgot to take his medications or something like that)
	 * then any one of the requested codes sent to user will be considered valid.
	 *
	 * @return object $this
	 *
	 * @throws LampcmsException in case the
	 * supplied code is invalid OR older than 24 hours
	 *
	 */
	protected function validateCode()
	{
		$timeOffset = (time() - 86500);
		$uid = (int)$this->oRequest['uid'];

		$aResult = $this->oRegistry->Mongo->getCollection('PASSWORD_CHANGE')
		->findOne(array('_id' => $this->oRequest['r'], 'i_uid' => $uid));

		d('$aResult '.print_r($aResult, true));

		if(empty($aResult)){

			$this->saveFailedAttempt()->oRegistry->Dispatcher->post($this, 'onFailedPasswordReset');

			throw new \Lampcms\Exception('wrong_password_reset_code');
		}


		if($aResult['i_ts'] < $timeOffset){
			d('code expired');

			throw new \Lampcms\Exception('password_reset_code_expired');
		}


		if(!empty($aResult['i_used'])) {
			d('code used');

			throw new \Lampcms\Exception('This password reset link was already used on '.date('r', $aResult['i_used'] ));
		}

		$aVal = $this->oRegistry->Mongo->getCollection('USERS')
		->findOne(array('_id' => (int)$aResult['i_uid']));

		$this->username = $aVal['username'];
		$this->email = $aVal['email'];
		$this->markCodeUsed();

		return $this;

	}


	/**
	 * Once the password reset code has been
	 * validated we should delete it so that it cannot
	 * be reused. This is both for security reason
	 * and so that the same user cannot accidentely change
	 * the password again by clicking on the
	 * same link in email
	 *
	 * @return object $this
	 */
	protected function markCodeUsed()
	{
		$newdata = array('$set' => array( 'i_used' => time()));

		$this->oRegistry->Mongo->getCollection('PASSWORD_CHANGE')
		->update(array('_id' => $this->oRequest['r']), $newdata);

		return $this;
	}


	/**
	 * Saves geodata from where the failed
	 * reset password attempt came from
	 *
	 * @return object $this
	 */
	protected function saveFailedAttempt()
	{
		$ip = Request::getIP();

		$aData = array(
		'i_uid' => (int)$this->oRequest['uid'],
        'i_ts' => time() );

		$res = $this->saveResourceLocation('1', $ip, $aData, 'PASSWORD_CHANGE');

		d('$res: '.$res);

		return $this;
	}


	/**
	 * Check for previous
	 * failed attempts to reset password
	 * by using incorrect code
	 *
	 * @throws LampcmsException in case
	 * over 5 previous failed attempts
	 * in past 24 hours
	 * detected from
	 * the same ip address or for the same
	 * userid
	 *
	 * @return object $this
	 */
	protected function checkHacks()
	{
		$ipHacks = 0;
		$uidHacks = 0;

		$timeOffset = time() - 86400;
		$cur = $this->oRegistry->Mongo->getCollection('PASSWORD_CHANGE')
		->find(array('i_ts' > $timeOffset));

		if($cur && ($cur->count(true) > 0) ){

			$ip = Request::getIP();

			foreach($cur as $aVal){
				if($ip == $aVal['ip']){
					$ipHacks += 1;
				}

				if($this->oRequest['uid'] == $aVal['i_uid'] ){
					$uidHacks += 1;
				}

				if($uidHacks > 5 || $ipHacks > 5){
					e('LampcmsError: hacking of password reset link. $uidHacks: '.$uidHacks. ' $ipHacks: '.$ipHacks.' from ip: '.$ip);

					$this->oRegistry->Dispatcher->post($this, 'onPasswordResetHack', $aVal);

					throw new \Lampcms\Exception('access_denied');
				}
			}
		}

		return $this;
	}


	/**
	 * Send out the new password to user
	 *
	 */
	protected function emailPwd()
	{
		$body = vsprintf(self::EMAIL_BODY, array($this->oRegistry->Ini->SITE_NAME, $this->username, $this->newPwd));
		$subject = sprintf(self::SUBJECT, $this->oRegistry->Ini->SITE_NAME);

		\Lampcms\Mailer::factory($this->oRegistry)->mail($this->email, $subject, $body);

		return $this;
	}


}
