The folder Livedata must be located in the route:
app/code/community

The file Livedata_Trans.xml must be located in the route:
etc/modules
 mv /var/www/magento/app/code/community/Livedata/Livedata_Trans.xml /var/www/magento/app/etc/modules/

Install the smtp pluggin from:
https://www.magentocommerce.com/magento-connect/smtp-pro-email-free-custom-smtp-email.html
"http://connect20.magentocommerce.com/community/ASchroder_SMTPPro"

Edit crontab:
contrab -e
Linux:
10 9 * * * /{{url to project}}/cron.sh
13 9 * * * /{{url to project}}/cron.sh
windows:
10 9 * * * /{{url to project}}/cron.php      //10 9 * * * /var/www/magento/cron.sh
13 9 * * * /{{url to project}}/cron.php

chmod 775 in all Livedata project

clear chache