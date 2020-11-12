#!/bin/bash

#exit-codes:
# 0	Alles OK
# 1 	Buildvariablen konnten nicht geladen werden
# 2	kritischer Linter fehlgeschlagen
# 3	Datenbak konnte nicht erstellt werden
# 4	Datenbakbenutzer konnte nicht erstellt werden
# 5	Datenbakrechte konnten nicht gesetzt werden
# 6	Dump konnte nicht in Datenbank eingespielt werden
# 7	Joomla Konfigurationsdatei konnte nicht erstellt werden
# 8	Schema-Tabelle konnte nicht aktualisiert werden.
# 9	Admin-Benutzer konnte nicht in die Usergroup-Map eingetragen werden.
# 10	Datenbankdump konnte nicht erstellt werden.
# 11	Datenbank und Datenbankbenutzer konnten nicht gelöscht werden.

mysqlopts='-u root -proot'

echo "Buildumgebung wird initialisiert..."

echo "Verzeichnisstruktur wird angelegt..."
mkdir -p build/temp
echo "OK! Verzeichnisstruktur wurde angelegt."

build/scripts/shell/cleanup.sh extensions

echo "Buildvariablen werden eingelesen..."
. build/config/joomla.build.properties.default || exit 1
if [ -x joomla.build.properties ]; then
	echo "Projektspezifische Buildvariablen werden eingelesen..."
	. joomla.build.properties || exit 1
	echo "OK! Projektspezifische Buildvariablen wurden eingelesen!"
else
	echo "Es wurden keine projektspezifischen Buildvariablen gefunden."
fi
echo "OK! Buildvariablen wurden eingelesen."

echo "Erweiterungen aus ext-install werden in den extensions Ordner kopiert ..."
# TODO: $file php build/scripts/php/ext-copy.php --extinstall $file
echo "OK! Erweiterungen aus ext-install wurden kopiert."

echo "Beginn des Elementchecks..."
# TODO: php build/scripts/php/ext-element.php | tee -a build/reports/elementcheck/elementcheck.txt
echo "OK! Ende des Elementchecks."

echo "Beginn der SQL-Präfixprüfung..."
# TODO: php build/scripts/php/ext-checkTablePrefix.php | tee -a build/reports/sqlprefixcheck/sqlprefixcheck.txt
echo "OK! Ende der SQL-Präfixprüfung."

echo "Joomla! auspacken, DB erstellen, Admin anlegen, Config anpassen..."
echo "Joomla-Version ${ci_joomla_version} wird installiert..."
unzip -u build/jpackages/*${ci_joomla_version}*.zip -d ./ > /dev/null && echo "OK! Joomla Archiv entpackt."
ci_jdb_name=$(php build/scripts/php/joomla-prepare.php --dbname)
echo "OK! Datenbankname ${ci_jdb_name} bezogen...."
ci_jdb_user=$(php build/scripts/php/joomla-prepare.php --dbuser)
echo "OK! Datenbankbenutzer ${ci_jdb_user} bezogen."

echo "Datenbankmodus wird gesetzt..."
mysql $mysqlopts -e "SET GLOBAL sql_mode = 'NO_ENGINE_SUBSTITUTION';"
if [ $? -eq "0" ]; then
	echo "OK! Datenbankmodus wurde gesetzt."
else
	echo "FAIL! Datenbankmodus konnte nicht gesetzt werden!"
	exit 2
fi

echo "Datenbank wird erstellt..."
mysql $mysqlopts -e "CREATE DATABASE IF NOT EXISTS ${ci_jdb_name} CHARACTER SET utf8;"
if [ $? -eq "0" ]; then
	echo "OK! Datenbank wurde erstellt."
else
	echo "FAIL! Datenbank konnte nicht erstellt werden."
	exit 3
fi

echo "Datenbanknutzer wird erstellt..."
mysql $mysqlopts -e "CREATE USER '${ci_jdb_user}'@'localhost' IDENTIFIED BY '${ci_jdb_password}';"
if [ $? -eq "0" ]; then
	echo "OK! Datenbankbenutzer erstellt."
else
	echo "FAIL! Datenbankbenutzer konnte nicht erstellt werden."
	exit 4
fi

echo "Datenbakrechte werden gesetzt..."
#mysql -u "root" -p"root" -e "GRANT ALL PRIVILEGES ON '${ci_jdb_name}' . * TO '${ci_jdb_user}'@'localhost'"
mysql $mysqlopts -e "GRANT ALL PRIVILEGES ON *.* TO '${ci_jdb_user}'@'localhost'; FLUSH PRIVILEGES;"
if [ $? -eq "0" ]; then
	echo "OK! Datenbankrechte gesetzt."
else
	echo "FAIL! Datenbankrechte konnten nicht gesetzt werden."
	exit 5
fi

echo "Präfix im Datenbankdump ersetzten..."
sed -i "s/#__/${ci_jdb_prefix}/" installation/sql/mysql/joomla.sql
echo "OK! Präfix in Datenbank ersetzt."

echo "Dump wir in Datenbank eingespielt..."
mysql -u "${ci_jdb_user}" -p"${ci_jdb_password}" -h "localhost" -P 3306 "${ci_jdb_name}" < installation/sql/mysql/joomla.sql
if [ $? -eq "0" ]; then
	echo "OK! Dump in Datenbank eingespielt."
else
	echo "FAIL! dump konnte nicht in Datenbank eingespielt werden."
	exit 6
fi

echo "Joomla Konfigurationsdatei wird erstellt..."
php build/scripts/php/joomla-prepare.php --config --db_host="localhost" --db_user="${ci_jdb_user}" --db_pass="${ci_jdb_password}" --db_name="${ci_jdb_name}" --db_prefix="${ci_jdb_prefix}"
if [ $? -eq "0" ]; then
	echo "OK! Joomla Konfigurationsdatei erstellt."
else
	echo "FAIL! joomla Konfigurationsdatei konnte nicht erstellt werden."
	exit 7
fi

echo "Joomla Ordner 'installation' wird umbenannt..."
mv installation instalation_old
echo "OK! Joomla Ordner 'installation' wurde umbenannt."

echo "Versionsnummer wird ermittelt..."
ci_jversion=$(php build/scripts/php/joomla-prepare.php --version)
ci_jthree=$(php build/scripts/php/joomla-prepare.php --jthree ${ci_jversion})
echo "OK! Versionsnummer ${ci_jversion} ermittelt."

echo "Schema-Tabelle wird aktualisiert..."
mysql -u "${ci_jdb_user}" -p"${ci_jdb_password}" -h "localhost" -P 3306 -e "INSERT INTO ${ci_jdb_name}.${ci_jdb_prefix}schemas (\`extension_id\`, \`version_id\`) VALUES (700, \"${ci_jversion}\")"
if [ $? -eq "0" ]; then
	echo "OK! Schema-Tabelle wurde aktualisiert."
else
	echo "FAIL! Schema-Tabelle konnte nicht aktualisiert werden."
	exit 8
fi

echo "Passwort des Joomla Admin-Users wird verschlüsselt..."
ci_joomla_password_crypt=$(php build/scripts/php/joomla-prepare.php --cryptpass="${ci_joomla_password}")
echo "OK! Passwort der Joomla Admin-Users wurde verschlüsselt."

if [ "${ci_jthree}" = "true"  ]; then
	echo "Joomla 3.x Admin Benutzer wird angelegt ..."
	mysql -u ${ci_jdb_user} -p${ci_jdb_password} -h "localhost" -P 3306 -e "INSERT INTO ${ci_jdb_name}.${ci_jdb_prefix}users (id, name, username, email, password, block, sendEmail, registerDate, lastvisitDate, activation, params, lastResetTime, resetCount) VALUES (7, \"Super User\", \"${ci_joomla_user}\", \"${ci_joomla_mail}\", \"${ci_joomla_password_crypt}\", 0, 1, \"2013-03-20 00:00:00\", \"0000-00-00 00:00:00\", 0, \"\", \"0000-00-00 00:00:00\", 0)"
else
	echo "Joomla 2.x Admin Benutzer wird angelegt ..."
	mysql -u ${ci_jdb_user} -p${ci_jdb_password} -h "localhost" -P 3306 -e "INSERT INTO ${ci_jdb_name}.${ci_jdb_prefix}users (id, name, username, email, password, usertype, block, sendEmail, registerDate, lastvisitDate, activation, params, lastResetTime, resetCount) VALUES (7, \"Super User\", \"${ci_joomla_user}\", \"${ci_joomla_mail}\", \"${ci_joomla_password_crypt}\", \"deprecated\", 0, 1, \"2013-03-20 00:00:00\", \"0000-00-00 00:00:00\", 0, \"\", \"0000-00-00 00:00:00\", 0)"
fi
if [ $? -eq "0" ]; then
	echo "OK! Joomla Admin Benutzer wurde angelegt."
else
	echo "FAIL! Joomla Admin Benutzer konnte nicht angelegt werden!"
	exit 9;
fi

echo "Admin-User wird in die Usergroup-Map eintragen..."
mysql -u ${ci_jdb_user} -p${ci_jdb_password} -h "localhost" -P 3306 -e "INSERT INTO ${ci_jdb_name}.${ci_jdb_prefix}user_usergroup_map (user_id, group_id) VALUES (7, 8)"
if [ $? -eq "0" ]; then
	echo "OK! Admin-Benutzer wurde in die Usergroup-Map eingetragen."
else
	echo "FAIL! Admin-Benutzer konnte nicht in die Usergroup-Map eingetragen werden."
	exit 10
fi

echo "OK! Joomla! wurde erfolgreich auf dem System eingerichtet."

echo "Datenbankdump wird erstellt..."
echo "Name der Datenbank wird ermittelt ..."
ci_jdbconfig_db=$(php build/scripts/php/db-dump.php --jdbname)
echo "OK! Datenbankname ${ci_jdbconfig_db} ermittelt."
mysqldump ${mysqlopts} ${ci_jdbconfig_db} > build/temp/${ci_sqldump}
if [ $? -eq "0" ]; then
	echo "OK! Datenbankdump ${ci_sqldump} wurde erfolgreich erstellt."
else
	echo "FAIL! Datenbankdump konnte nicht erstellt werden."
	exit 11
fi

echo "Datenbank ${ci_jdb_name} wird gelöscht..."
mysql $mysqlopts -e "DROP DATABASE ${ci_jdb_name};"
if [ $? -eq "0" ]; then
	echo "OK! Datenbank ${ci_jdb_name} wurde erfolgreich gelöscht."
else
	echo "FAIL! Datenbank ${ci_jdb_name} konnte nicht gelöscht werden."
	exit 12
fi

echo "Datenbanknutzer ${ci_jdb_user} wird entfernt..."
mysql $mysqlopts -e "DELETE FROM mysql.user WHERE User='${ci_jdb_user}'; FLUSH PRIVILEGES;"
if [ $? -eq "0" ]; then
	echo "OK! Benutzer ${ci_jdb_user} wurde erfolgreich entfernt."
else
	echo "FAIL! Benutzer ${ci_jdb_user} konnte nicht entfernt werden."
	exit 13
fi

echo "OK! Buildumgebung wurde initialisiert."

echo "Neue Datenbank wird wiederhergestellt..."
php build/scripts/php/db-create.php --dbhost='localhost' --dbport='3306' --dbuser='root' --dbpass='root' --dump="build/temp/${ci_sqldump}" --jdbpass="${ci_jdb_password}" --jdbprefix="${ci_jdb_prefix}" || exit 14
echo "OK! Datenbank wurde wiederhergestellt."

echo "Datenbankdump wird gelöscht..."
rm "build/temp/${ci_sqldump}"
echo "OK! Datenbankdump wurde gelöscht."

echo "Sortiere alle Erweiterungen..."
php build/scripts/php/ext-sort.php --extxmlfile=${ci_file_ext_install} || exit 15
echo "OK! Erweiterungen wurden sortiert."

echo "Beginn der Installation aller Erweiterungen..."
php build/scripts/php/ext-install.php --user "${ci_joomla_user}" --password "${ci_joomla_password}"
if [ -e "build/temp/extinstalltempfile" ]; then
	echo "FAIL! Eine der Erweiterungen konnte nicht installiert werden."
	exit 16
else
	echo "OK! Alle Erweiterungen wurden installiert."
fi

echo "Beginn der Deinstallation aller Erweiterungen..."
php build/scripts/php/ext-uninstall.php --user "${ci_joomla_user}" --password "${ci_joomla_password}"
if [ -e "build/temp/extuninstalltempfile" ]; then
	echo "FAIL! Eine der Erweiterungen konnte nicht deinstalliert werden."
	exit 17
else
	echo "OK! Alle Erweiterungen wurden deinstalliert."
fi

echo "Beginn der erneuten Installation aller Erweiterungen..."
php build/scripts/php/ext-install.php --user "${ci_joomla_user}" --password "${ci_joomla_password}"
if [ -e "build/temp/extinstalltempfile" ]; then
	echo "FAIL! Eine der Erweiterungen konnte nicht erneut installiert werden."
	exit 18
else
	echo "OK! Alle Erweiterungen wurden erneut installiert."
fi

echo "Datenbankdump wird erstellt..."
echo "Name der Datenbank wird ermittelt ..."
ci_jdbconfig_db=$(php build/scripts/php/db-dump.php --jdbname)
echo "OK! Datenbankname ${ci_jdbconfig_db} ermittelt."
mysqldump -u "root" -p"root" -h "localhost" -P 3306 ${ci_jdbconfig_db} > build/temp/${ci_sqldump}
if [ $? -eq "0" ]
then
	echo "OK! Datenbankdump ${ci_sqldump} wurde erfolgreich erstellt."
else
	echo "FAIL! Datenbankdump konnte nicht erstellt werden."
	exit 19
fi

exit 0
