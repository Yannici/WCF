{include file='documentHeader'}

<head>
	<title>{lang}wcf.moderation.deletedContent.objectType.{@$objectType}{/lang} - {PAGE_TITLE|language}</title>
	
	{include file='headInclude'}
</head>

<body id="tpl{$templateName|ucfirst}" data-template="{$templateName}" data-application="{$templateNameApplication}">

{capture assign='sidebarLeft'}
	<section class="box">
		<h2 class="boxTitle">{lang}wcf.moderation.deletedContent.objectTypes{/lang}</h2>
		
		<div class="boxContent">
			<nav>
				<ul>
					{foreach from=$availableObjectTypes item=availableObjectType}
						<li{if $objectType == $availableObjectType->objectType} class="active"{/if}><a href="{link controller='DeletedContentList'}objectType={@$availableObjectType->objectType}{/link}">{lang}wcf.moderation.deletedContent.objectType.{@$availableObjectType->objectType}{/lang}</a></li>
					{/foreach}
				</ul>
			</nav>
		</div>
	</section>
{/capture}

{include file='header'}

<header class="contentHeader">
	<div class="contentHeaderTitle">
		<h1 class="contentTitle">{lang}wcf.moderation.deletedContent.{@$objectType}{/lang}</h1>
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

{hascontent}
	<div class="paginationTop">
		{content}{pages print=true assign=pagesLinks controller='DeletedContentList' link="objectType=$objectType&pageNo=%d"}{/content}
	</div>
{/hascontent}

{if $items}
	{include file=$resultListTemplateName application=$resultListApplication}
{else}
	<p class="info">{lang}wcf.global.noItems{/lang}</p>
{/if}

<footer class="contentFooter">
	{hascontent}
		<div class="paginationBottom">
			{content}{@$pagesLinks}{/content}
		</div>
	{/hascontent}
	
	{hascontent}
		<nav class="contentFooterNavigation">
			<ul>
				{content}{event name='contentFooterNavigation'}{/content}
			</ul>
		</nav>
	{/hascontent}
</footer>

{include file='footer'}

</body>
</html>