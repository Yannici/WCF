{include file='documentHeader'}

<head>
	<title>{lang}wcf.user.emailActivation{/lang} - {PAGE_TITLE|language}</title>
	
	{include file='headInclude'}
</head>

<body id="tpl{$templateName|ucfirst}" data-template="{$templateName}" data-application="{$templateNameApplication}">

{include file='header'}

<header class="contentHeader">
	<div class="contentHeaderTitle">
		<h1 class="contentTitle">{lang}wcf.user.emailActivation{/lang}</h1>
	</div>
	
	{hascontent}
		<nav class="contentHeaderNavigation">
			<ul>
				{content}{event name='contentHeaderNavigation'}{/content}
			</ul>
		</nav>
	{/hascontent}
</header>

{include file='userNotice'}

{include file='formError'}

<form method="post" action="{link controller='EmailActivation'}{/link}">
	<div class="section">
		<dl{if $errorField == 'u'} class="formError"{/if}>
			<dt><label for="userID">{lang}wcf.user.userID{/lang}</label></dt>
			<dd>
				<input type="text" id="userID" name="u" value="{@$u}" required="required" class="medium" />
				{if $errorField == 'u'}
					<small class="innerError">
						{if $errorType == 'notValid'}{lang}wcf.user.userID.error.invalid{/lang}{/if}
					</small>
				{/if}
			</dd>
		</dl>
		
		<dl{if $errorField == 'a'} class="formError"{/if}>
			<dt><label for="activationCode">{lang}wcf.user.activationCode{/lang}</label></dt>
			<dd>
				<input type="text" id="activationCode" maxlength="9" name="a" value="{@$a}" required="required" class="medium" />
				{if $errorField == 'a'}
					<small class="innerError">
						{if $errorType == 'notValid'}{lang}wcf.user.activationCode.error.notValid{/lang}{/if}
					</small>
				{/if}
				<small><a href="{link controller='EmailNewActivationCode'}{/link}">{lang}wcf.user.newActivationCode{/lang}</a></small>
			</dd>
		</dl>
		
		{event name='fields'}
	</div>
	
	{event name='sections'}
	
	<div class="formSubmit">
		<input type="submit" value="{lang}wcf.global.button.submit{/lang}" accesskey="s" />
		{@SECURITY_TOKEN_INPUT_TAG}
	</div>
</form>

{include file='footer'}

</body>
</html>
