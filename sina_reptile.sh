#!/bin/bash
cd /www/sina_reptile/;php index.php &
while true; do
proc=`ps -ef |grep "index.php"|wc -l`
if [ $proc -lt 15 ];then
 cd /www/sina_reptile/;php index.php &
fi
done