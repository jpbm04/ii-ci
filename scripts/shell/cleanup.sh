#!/bin/bash

#exit-codes:
# 0	Alles OK
# 1	Skript wurde falsch aufgerufen
# 2	Der gegebene Parameter ist kein Verzeichnis

if [ $# -eq 1 ]
then
	if [ -d "$1" ]
	then
		echo "Buildumgebung im Verzeichnis \"${1}\" wird bereinigt..."
		find "$1" -type d -name ".git" -exec rm -rf "{}" \;
		find "$1" -type d -name ".github" -exec rm -rf "{}" \;
		find "$1" -name ".gitignore" -delete
		find "$1" -name ".gitmodules" -delete
		find "$1" -name ".sonarcloud.properies" -delete
		echo "OK! Buildumgebung wurde bereinigt."
	else
		echo "FAIL! $1 ist kein Verzeichnis" > /dev/stderr
		exit 2
	fi
else
	echo "FAIL! Skript wurde falsch aufgerufen." > /dev/stderr
	echo "Aufruf: cleanup.sh <Zu bereinigendes Verzeichnis>"
	exit 1
fi

exit 0
