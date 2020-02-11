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

echo "Buildumgebung wird initialisiert..."

echo "Buildvariablen werden eingelesen..."
. build/config/joomla.build.properties.default || exit 1
if [ -x joomla.build.properties ]
then
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

echo "Datenbank wird erstellt..."
mysql -u "root" -p"root" -e "CREATE DATABASE IF NOT EXISTS ${ci_jdb_name} CHARACTER SET utf8"
if [ $? -eq "0" ]
then
	echo "OK! Datenbank wurde erstellt."
else
	echo "FAIL! Datenbank konnte nicht erstellt werden."
	exit 3
fi

echo "Datenbanknutzer wird erstellt..."
mysql -u "root" -p"root" -e "CREATE USER '${ci_jdb_user}'@'localhost' IDENTIFIED BY '${ci_jdb_password}'"
if [ $? -eq "0" ]
then
	echo "OK! Datenbankbenutzer erstellt."
else
	echo "FAIL! Datenbankbenutzer konnte nicht erstellt werden."
	exit 4
fi

echo "Datenbakrechte werden gesetzt..."
mysql -u "root" -p"root" -e "GRANT ALL PRIVILEGES ON '${ci_jdb_name}' . * TO '${ci_jdb_user}'@'localhost'"
if [ $? -eq "0" ]
then
	echo "OK! Datenbankrechte gesetzt."
else
	echo "FAIL! Datenbankrechte konnten nicht gesetzt werden."
	exit 5
fi

echo "Präfix im Datenbankdump ersetzten..."
sed -i "s/#__/${ci_jdb_prefix}/" installation/sql/mysql/joomla.sql
echo "OK! Präfix in Datenbank ersetzt."

echo "Dump wir in Datenbank eingespielt..."
mysql -u "${ci_jdb_user}" -p"${ci_jdb_password}" "${ci_jdb_name}" < installation/sql/mysql/joomla.sql
if [ $? -eq "0" ]
then
	echo "OK! Dump in Datenbank eingespielt."
else
	echo "FAIL! dump konnte nicht in Datenbank eingespielt werden."
	exit 6
fi

echo "Joomla Konfigurationsdatei wird erstellt..."
php build/scripts/php/joomla-prepare.php --config --db_host="localhost" --db_user="${ci_jdb_user}" --db_pass="${ci_jdb_password}" --db_name="${ci_jdb_name}" --db_prefix="${ci_jdb_prefix}"
if [ $? -eq "0" ]
then
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
mysql -u "${ci_jdb_user}" -p"${ci_jdb_password}" -e "INSERT INTO ${ci_jdb_name}.${ci_jdb_prefix}schemas (\`extension_id\`, \`version_id\`) VALUES (700, \"${ci_jversion}\")"
if [ $? -eq "0" ]
then
	echo "OK! Schema-Tabelle wurde aktualisiert."
else
	echo "FAIL! Schema-Tabelle konnte nicht aktualisiert werden."
	exit 8
fi

echo "Passwort des Joomla Admin-Users wird verschlüsselt..."
ci_joomla_password_crypt=$(php build/scripts/php/joomla-prepare.php --cryptpass="${ci_joomla_password}")
echo "OK! Passwort der Joomla Admin-Users wurde verschlüsselt."


if [ "${ci_jthree}" = "true"  ]
then
	echo "Joomla 3.x Admin Benutzer wird angelegt ..."
	mysql -u ${ci_jdb_user} -p${ci_jdb_password} -e "INSERT INTO ${ci_jdb_name}.${ci_jdb_prefix}users (id, name, username, email, password, block, sendEmail, registerDate, lastvisitDate, activation, params, lastResetTime, resetCount) VALUES (7, \"Super User\", \"${ci_joomla_user}\", \"${ci_joomla_mail}\", \"${ci_joomla_password_crypt}\", 0, 1, \"2013-03-20 00:00:00\", \"0000-00-00 00:00:00\", 0, \"\", \"0000-00-00 00:00:00\", 0)"
else
	echo "Joomla 2.x Admin Benutzer wird angelegt ..."
	mysql -u ${ci_jdb_user} -p${ci_jdb_password} -e "INSERT INTO ${ci_jdb_name}.${ci_jdb_prefix}users (id, name, username, email, password, usertype, block, sendEmail, registerDate, lastvisitDate, activation, params, lastResetTime, resetCount) VALUES (7, \"Super User\", \"${ci_joomla_user}\", \"${ci_joomla_mail}\", \"${ci_joomla_password_crypt}\", \"deprecated\", 0, 1, \"2013-03-20 00:00:00\", \"0000-00-00 00:00:00\", 0, \"\", \"0000-00-00 00:00:00\", 0)"
fi
echo "OK! Joomla Admin Benutzer wurde angelegt."

echo "Admin-User wird in die Usergroup-Map eintragen..."
mysql -u ${ci_jdb_user} -p${ci_jdb_password} -e "INSERT INTO ${ci_jdb_name}.${ci_jdb_prefix}user_usergroup_map (user_id, group_id) VALUES (7, 8)"
if [ $? -eq "0" ]
then
	echo "OK! Admin-Benutzer wurde in die Uusergroup-Map eingetragen."
else
	echo "FAIL! Admin-Benutzer konnte nicht in die Usergroup-Map eingetragen werden."
	exit 9
fi

echo "OK! Joomla! wurde erfolgreich auf dem System eingerichtet."

echo "Datenbankdump wird erstellt..."
echo "Name der Datenbank wird ermittelt ..."
ci_jdbconfig_db=$(php build/scripts/php/db-dump.php --jdbname)
echo "OK! Datenbankname ${ci_jdbconfig_db} ermittelt."
mysqldump ${ci_jdbconfig_db} > build/temp/dumps/${ci_sqldump_a}
if [ $? -eq "0" ]
then
	echo "OK! Datenbankdump ${ci_sqldump_a} wurde erfolgreich erstellt."
else
	echo "FAIL! Datenbankdump konnte nicht erstellt werden."
	exit 10
fi

echo "OK! Buildumgebung wurde initialisiert."

exit 0
