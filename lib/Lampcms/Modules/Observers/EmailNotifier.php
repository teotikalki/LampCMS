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
 *    the website\'s Questions/Answers functionality is powered by lampcms.com
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


namespace Lampcms\Modules\Observers;

use \Lampcms\Mailer;

class EmailNotifier extends \Lampcms\Observer
{
	const QUESTION_BY_USER_BODY = '
	%1$s has asked a question:
	%2$s
	
	-------------
	%3$s

	
	Visit this url %4$s
	to read the entire question
	and try to answer it if you can.
	
	
	====
	You receive this message because you are following
	the user %1$s.
	
	You can change your email preferences by signing in to 
	site %5$s and navigating to Settings > Email preferences
	
	';


	const ANSWER_BY_USER_BODY = '
	%1$s has answered a question:
	%2$s

	
	Visit this url %4$s
	to read the entire question
	and the answer
	
	
	----
	You receive this message because you are following
	the user %1$s.
	
	You can change your email preferences by signing in to 
	site %5$s and navigating to Settings > Email preferences
	
	';


	const QUESTION_BY_TAG_BODY = '
	%1$s has asked a question:
	%2$s
	
	-------------
	%3$s

	
	Visit this url %4$s
	to read the entire question
	and try to answer it if you can.
	
	
	----
	You receive this message because question contains one
	of the tags you follow
	
	You can change your email preferences by signing in to 
	site %5$s and navigating to Settings > Email preferences
	
	';
	
	
	const QUESTION_FOLLOW_BODY = '
	%1$s has added a %2$s
	to a question you follow:
	%3$s
	
	Visit this url %4$s
	to read the entire question
	
	
	
	----
	You receive this message because you are
	following this question
	
	You can change your email preferences by signing in to 
	site %5$s and navigating to Settings > Email preferences
	
	';

	const QUESTION_BY_USER_SUBJ = 'New %s by %s';

	const QUESTION_BY_TAG_SUBJ = 'New question tagged: [%s]';
	
	const QUESTION_FOLLOW_SUBJ = 'New %s to a question you following';


	/**
	 * UserID of author
	 *
	 * @var int
	 */
	protected $author_id = 0;


	protected $oQuestion;

	
	/**
	 * @todo Finish this by adding handling 
	 * updates onNewComment, onEditedQuestion, onQuestionVote,
	 * onAcceptAnswer, etc...
	 * 
	 * (non-PHPdoc)
	 * @see Lampcms.Observer::main()
	 */
	public function main(){
		d('get some event: '.$this->eventName);
		switch ($this->eventName){
			case 'onNewQuestion':
				$this->oQuestion = $this->obj;
				$this->notifyUserFollowers();
				$this->notifyTagFollowers();
				break;

			case 'onNewAnswer':
				$this->oQuestion = $this->aInfo['question'];
				$this->notifyUserFollowers();
				$this->notifyQuestionFollowers();
				break;

		}

	}


	/**
	 * Get all users that follow any of the tags in question
	 * BUT NOT following the Question owner because
	 * we already sending out emails to all
	 * who following question owner.
	 *
	 * This is an easy way to avoid sending out emails twice
	 * in case user happens to follow Question asker and
	 * one of the tags in question
	 *
	 * Also exclude the id of question author, in case
	 * author is also following one of the tags in question
	 * the author does not have to be notified
	 * of own question.
	 *
	 * The cursor is then passed to Mailer object
	 *
	 * @return object $this
	 */
	protected function notifyTagFollowers(){
		$askerID = $this->oQuestion->getOwnerId();
		$cur = $this->oRegistry->Mongo->USERS
		->find(array('a_f_t' => array('$in' => $this->oQuestion['a_tags'] ), 'a_f_u' => array('$ne' => $askerID ), '_id' => array('$ne' => $askerID) ), array('email', 'e_ft')  );


		$count = $cur->count();
		d('found: '.$count.' items ');

		if($count > 0){
			$subj = sprintf(self::QUESTION_BY_TAG_SUBJ, implode(', ', $this->oQuestion['a_tags']) );
			$body = vsprintf(self::QUESTION_BY_TAG_BODY, array($this->oQuestion['username'], $this->oQuestion['title'], $this->oQuestion['intro'], $this->oQuestion->getUrl(), $this->oRegistry->Ini->SITE_URL));

			$oMailer = new Mailer($this->oRegistry);
			/**
			 * @todo pass callback function
			 * to exclude mailing to those who
			 * opted out on Email On Followed Tag
			 */
			$oMailer->mail($cur, $subj, $body);
		}
		
		return $this;
	}


	/**
	 * Notify all followers if question
	 * asker.
	 *
	 * @return object $this
	 */
	protected function notifyUserFollowers(){

		$uid = $this->obj->getOwnerId();
		d('uid: '.$uid);
		
		$cur = $this->oRegistry->Mongo->USERS
		->find(array('a_f_u' => $uid ), array('email', 'e_fu')  );

		$count = $cur->count();
		d('found: '.$count.' items ');

		if($count > 0){
			/**
			 * @todo in case of Answer use different
			 * templates for SUBJ and BODY
			 *
			 * @var unknown_type
			 */
			$tpl = self::QUESTION_BY_USER_BODY;
			$updateType = 'question';
			if('onNewAnswer' === $this->eventName){
				$tpl = self::ANSWER_BY_USER_BODY;
				$updateType = 'answer';
			}
			
 			$subj = sprintf(self::QUESTION_BY_USER_SUBJ, $updateType, $this->obj['username']);
			$body = vsprintf($tpl, array($this->obj['username'], $this->oQuestion['title'], '', $this->obj->getUrl(), $this->oRegistry->Ini->SITE_URL));

			$oMailer = new Mailer($this->oRegistry);
			/**
			 * @todo pass callback function
			 * to exclude mailing to those who
			 * opted out on Email On Followed User
			 */
			$oMailer->mail($cur, $subj, $body);
		}
		
		return $this;
	}


	/**
	 * Notify all who follows the question
	 * But exclude the Viewer - whoever just added
	 * the new answer or whatever
	 *
	 *
	 * and exclude all who follows the Viewer because all who
	 * follows the Viewer will be notified via
	 * the nofityUserFollowers
	 *
	 * @return object $this
	 */
	protected function notifyQuestionFollowers($qid = null){
		$viewerID = $this->oRegistry->Viewer->getUid();
		/**
		 * 
		 * $qid can be passed here 
		 * OR in can be extracted from $this->oQuestion
		 * 
		 */
		$qid = ($qid) ? (int)$qid : $this->oQuestion->getResourceId();

		$cur = $this->oRegistry->Mongo->USERS
		->find(array('a_f_q' => $qid, 'a_f_u' => array('$ne' => $viewerID ), '_id' => array('$ne' => $viewerID) ), array('email', 'e_fq')  );

		$count = $cur->count();
		d('found: '.$count.' items ');

		if($count > 0){		

			$updateType = ('onNewAnswer' === $this->eventName) ? 'answer' : 'comment';
			$subj = sprintf(self::QUESTION_FOLLOW_SUBJ, $updateType);
			$body = vsprintf(self::QUESTION_FOLLOW_BODY, array($this->obj['username'], $updateType, $this->oQuestion['title'], $this->obj->getUrl(), $this->oRegistry->Ini->SITE_URL));

			$oMailer = new Mailer($this->oRegistry);
			/**
			 * @todo pass callback function
			 * to exclude mailing to those who
			 * opted out on Email On Followed Question
			 */
			$oMailer->mail($cur, $subj, $body);
		}
		
		return $this;
	}
	
}
