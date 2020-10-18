{_T string="Translation, no domain"}
{* Not any other syntax here, just to produce same strings as php *}
{_T string="Another known syntax"}
{_T string="Translation, galette domain" domain="galette"}
{_T string="Translation, other domain" domain="other"}

{_T string="I have a dream" plural="I have several dreams" count=1}
{_T string="Cancel" context="button"}
{_T context="button" string="Proceed action" plural="Proceed actions" count=3}

{_T string="Hello %s" pattern="/%s/" replace="you" comment="%s will be replaced with user name"}
{_T string="Day is %1\$s, hour is %2\$s" pattern=["/%1\$s/", "/%2\$s/"] replace=["tuesday", 9] comment="First parameter is the day name, second the hour in the day"}
