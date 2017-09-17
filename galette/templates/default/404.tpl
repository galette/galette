<!DOCTYPE html>
<html lang="{$galette_lang}" class="public_page{if $additionnal_html_class} {$additionnal_html_class}{/if}">
    <head>
        {include file='common_header.tpl'}
    </head>
    <body>
        {* IE8 and above are no longer supported *}
        <!--[if lte IE 8]>
        <div id="oldie">
            <p>{_T string="Your browser version is way too old and no longer supported in Galette for a while."}</p>
            <p>{_T string="Please update your browser or use an alternative one, like Mozilla Firefox (http://mozilla.org)."}</p>
        </div>
        <![endif]-->
        <div class="error">
            <header>
                <img src="{path_for name="logo"}" width="{$logo->getOptimalWidth()}" height="{$logo->getOptimalHeight()}" alt="[ Galette ]" />
            </header>
            <div id="errorbox">
                <h2>{_T string="Page not found"}</h2>
            </div>
            <p class="center">{_T string="Sorry, the page you are looking for could not be found."}</p>
            <nav>
                <a id="backhome" class="button{if $cur_route eq "slash" or $cur_route eq 'login'} selected{/if}" href="{path_for name="slash"}">{_T string="Home"}</a>
            </nav>
        </div>

        {include file="footer.tpl"}
    </body>
</html>
