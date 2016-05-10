#!/bin/bash
# script executer par Gammu lors de la reception d'un SMS
# variables d'environnement
# SMS_1_CLASS='-1'
# SMS_1_NUMBER= numero tel
# SMS_1_TEXT= message
# SMS_MESSAGES='1'  nbre de SMS
# en argument le fichier contenant le SMS
FILE=$1
MESSAGE=$SMS_1_TEXT
FROM=$SMS_1_NUMBER
LOG="#DIR#/../../../log/SMS.log"
INPUT="/var/spool/gammu/inbox/"

wget --no-check-certificate -qO- "#URL#&phone=$FROM&text=$MESSAGE"
echo `date`" JEEDOM SMS from "$FROM" : "$MESSAGE" file="$FILE" code="$? >> $LOG
rm $INPUT/$FILE
exit 0
