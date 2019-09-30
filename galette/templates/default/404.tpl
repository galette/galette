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
        <div class="ui container">
            <header>
                <img src="{path_for name="logo"}" width="{$logo->getOptimalWidth()}" height="{$logo->getOptimalHeight()}" alt="[ Galette ]" />
            </header>
            <div class="ui red message">
                <h2 class="ui center aligned header">{_T string="Page not found"}</h2>
            </div>
            <div class="ui basic center aligned segment">
                <p class="ui large text">{_T string="Sorry, the page you are looking for could not be found."}</p>
            </div>
            <nav>
                <a href="{path_for name="slash"}" class="ui labeled icon button">
                    <i class="home icon"></i>
                    {_T string="Home"}
                </a>
            </nav>
        </div>

        {include file="footer.tpl"}
    </body>
</html>
