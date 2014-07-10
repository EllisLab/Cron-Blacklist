# Cron-Blacklist

This plugin downloads the ExpressionEngine.com Blacklist and appends it to the site's
current Blacklist. You must have the Blacklist module installed *and* have your
license number filled out in the Admin section of the ExpressionEngine Control
Panel under General Configuration for this to work. Basically, think of it as
automating the link provided in the Blacklist module for this purpose.

If you post a question in the forum wondering why this plugin does not work
and you do not have your license number filled out, I will scold you mightily.

[Find your license number](http://expressionengine.com/knowledge_base/article/my_expressionengine_license_number/)

## EXAMPLES

Updates site blacklist with ExpressionEngine.com Blacklist at 5am every morning

    {exp:cron minute="0" hour="5" plugin="cron_blacklist"}{/exp:cron}

Version 1.1
******************
- Updated plugin to be 2.0 compatible

