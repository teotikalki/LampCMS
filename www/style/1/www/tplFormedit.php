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



class tplFormedit extends Lampcms\Template\Template
{
	/**
	 * Important: names of form fields
	 * must match the keys in this array
	 * for example 'title', 'body', 'tags'
	 * must be names of form fields
	 *
	 * @var array
	 */
	protected static $vars = array(
	'token' => '', //1
	'required' => 'required', //2
	'title' => '', //3
	'title_l' => 'Title', // 4
	'title_d' => '',
	'title_e' => '', // 6
	'title_c' => 'title', // 7
	'qbody' => '', //8
	'qbody_e' => '', //9
	'reason' => '', //10
	'reason_l' => 'Reason for editing', //11
	'reason_d' => 'Enter short summary of reason for this edit', //12
	'reason_e' => '', //13
	'submit' => 'Save', //14
	'rtype' => 'q', //15
	'formError' => '',// 16
	'hidden' => '', //17
	'id_title' => 'title_off', //18
	'id' => '' //19
	); 

	protected static $tpl = '
	<div id="edit_form">
	<div class="form_error">%16$s</div>
		<form class="qa_form" name="editor" method="POST" action="/index.php" accept-charset="utf-8">
		<input type="hidden" name="a" value="editor">	
		<input type="hidden" name="token" value="%1$s">
		<input type="hidden" name="rtype" value="%15$s">
		<input type="hidden" name="rid" value="%19$s">
		<div class="form_el%17$s"> 
                <label for="id_title">%4$s</label>: <span class="f_err">%6$s</span><br> 
                <input autocomplete="off" id="%18$s" class="title_c" type="text" name="title" size="80" value="%3$s"> 
                <div id="title_d" class="caption">%5$s</div> 
       </div>
       <!-- // el title -->
            <div class="form_el" id="iedit"> 
                <textarea id="id_qbody" rows="10" cols="40" class="com_body white" name="qbody">%8$s</textarea><br>
                <span class="f_err">%9$s</span>
                <div id="body_preview"></div>
                <span class="label">Preview</span>
                <div id="tmp_preview"></div>
            </div>
            <!-- // el body -->
            
            <div class="form_el"> 
            	<label for="id_reason">%11$s</label>: (* %2$s) <span class="f_err">%13$s</span><br> 
                <input autocomplete="off" id="id_reason" type="text" name="reason" class="reason_c" size="80" value="%10$s">  
            	<div id="reason_d" class="caption">%12$s</div> 
            </div>
            <!-- // el tags -->
            
            <div class="form_el">
            	<input id="dostuff" name="submit" type="submit" value="%14$s" class="btn btn-m"> 
            </div>
            <!-- // el submit -->
		</form>
	</div>';
}
