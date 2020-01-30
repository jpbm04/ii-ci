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

echo "Beginn der ersten Phase: Organizer.INIT..."

#TODO: Anders lösen
if [ "${ci_fresh_tagged_build}" == "true" ]; then
	echo "Dieser Build wurde getagged und wird um Redundanzen zu vermeiden nicht ausgeführt."
	exit 0
fi

echo "Buildvariablen werden eingelesen..."
. build/config/joomla.build.properties.default || exit 1
echo "OK! Buildvariablen wurden eingelesen!"
if [ -x joomla.build.properties ]
then
	echo "Projektspezifische Buildvariablen werden eingelesen..."
	. joomla.build.properties || exit 1
	echo "OK! Projektspezifische Buildvariablen wurden eingelesen!"
else
	echo "Es wurden keine projektspezifischen Buildvariablen gefunden."
fi

echo "Buildumgebung wird bereinigt..."
find ./tests -not -name "AllGuiTests.php" -not -name "AllUnitTests.php" -not -name "framework_include.php" -not -name "JoomlaSeleniumTest.php" -not -name "JoomlaWebdriverTestCase.php" -not -name "iCampusWebdriverTestCase.php" -not -name "DummyTest.php" -not -name "bootstrap.php" -not -name "bootstrapJ3.php" -not -name "bootstrapSelenium.php" -not -wholename "*/core/*" -not -wholename "*/SeleniumClient/*" -not -wholename "*/Pages/*" -delete 2> /dev/null
echo "OK! Buildumgebung wurde bereinigt."

echo "Buildumgebung wird geladen..."
mkdir -p build/api
mkdir -p build/code-browser
mkdir -p build/reports
mkdir -p build/reports/elementcheck
mkdir -p build/reports/linter/css/csslint
mkdir -p build/reports/linter/js/jshint
mkdir -p build/reports/linter/php
mkdir -p build/reports/linter/xml/xmllint
mkdir -p build/reports/sqlprefixcheck

mkdir -p build/reports/pdepend
mkdir -p build/reports/phploc
mkdir -p build/reports/phpmd
mkdir -p build/reports/phpcpd
mkdir -p build/reports/phpcs
mkdir -p build/reports/phpunit
mkdir -p build/reports/phpunit/log-junit
mkdir -p build/reports/phpunit/coverage-clover
mkdir -p build/reports/phpunit/coverage-html
mkdir -p build/reports/selenium
mkdir -p build/temp/dumps
mkdir extensions
mkdir updates
cp build/index.html updates/index.html
mkdir zips
cp build/index.html zips/index.html
php build/scripts/php/ext-delete.php
echo "OK! Buildumgebung wurde geladen."

echo "Erweiterungen werden kopiert..."
cp -r build/temp/extensions/* extensions/
echo "OK! Erweiterungen wurden kopiert."

echo "Buildumgebung wird initialisiert..."
#TODO: refspec?

echo "Erweiterungen aus ext-install werden in den extensions Ordner kopiert ..."
# TODO: $file php build/scripts/php/ext-copy.php --extinstall $file
echo "OK! Erweiterungen aus ext-install wurden kopiert."

echo "Beginn der Syntaxprüfung für PHP-Dateien..."
find ./extensions -name "*.php" -exec php -l {} \; | grep -v "No syntax errors detected in" | tee -a build/reports/linter/php/php.txt
echo "OK! Ende der Syntaxprüfung für PHP-Dateien."

echo "Beginn der Syntaxprüfung für XML-Dateien..."
find ./extensions -name *.xml -exec xmllint --noout {} + | tee -a build/reports/linter/xml/xmllint/xmllint.txt
echo "OK! Ende der Syntaxprüfung für XML-Dateien."

echo "Beginn der Syntaxprüfung für JS-Dateien..."
find ./extensions -name *.js -not -wholename "*.min.js" -not -wholename "*jscolor.js" -not -wholename "*/tristate*.js" -not -wholename "*/lib_jquery/*" -not -wholename "*/lib_jquery/*" -not -wholename "*/lib_extjs4/*" -not -wholename "*/extjs/ext-all.js" -not -wholename "*/extjs/ext-all-debug.js" -not -wholename "*/extjs/bootstrap-manifest.js" -not -wholename "*/js/jquery-ui-1.9.2.custom.js" -not -wholename "*/js/cropbox.js" -not -wholename "*/js/jquery.easing.js" -not -wholename "*/previewbutton.js" -exec jshint {} + | tee -a build/reports/linter/js/jshint/jshint.txt
echo "OK! Ende der Syntaxprüfung für JS-Dateien."

echo "Beginn der Syntaxprüfung für CSS-Dateien..."
find extensions -name *.css -exec java -jar build/tools/js.jar build/tools/csslint-rhino.js {} +
find extensions -name *.css -exec java -jar build/tools/js.jar build/tools/csslint-rhino.js --format=lint-xml {} + > build/reports/linter/css/csslint/csslint.xml
echo "OK! Ende der Syntaxprüfung für CSS-Software."

echo "Beginn des Elementchecks..."
php build/scripts/php/ext-element.php | tee -a build/reports/elementcheck/elementcheck.txt
echo "OK! Ende des Elementchecks."

echo "Beginn der SQL-Präfixprüfung..."
php build/scripts/php/ext-checkTablePrefix.php | tee -a build/reports/sqlprefixcheck/sqlprefixcheck.txt
echo "OK! Ende der SQL-Präfixprüfung."

echo "Joomla! auspacken, DB erstellen, Admin anlegen, Config anpassen..."
echo "Joomla-Version ${ci_joomla_version} wird installiert..."
unzip -u build/jpackages/*${ci_joomla_version}*.zip -d ./ > /dev/null && echo "OK! Joomla Archiv entpackt."
ci_jdb_name=$(php build/scripts/php/joomla-prepare.php --dbname)
echo "OK! Datenbankname ${ci_jdb_name} bezogen...."
ci_jdb_user=$(php build/scripts/php/joomla-prepare.php --dbuser)
echo "OK! Datenbankbenutzer ${ci_jdb_user} bezogen."

echo "Datenbank wird erstellt..."
mysql -e "CREATE DATABASE IF NOT EXISTS ${ci_jdb_name} CHARACTER SET utf8"
if [ $? -eq "0" ]
then
	echo "OK! Datenbank wurde erstellt."
else
	echo "FAIL! Datenbank konnte nicht erstellt werden."
	exit 3
fi

echo "Datenbanknutzer wird erstellt..."
mysql -e "CREATE USER ${ci_jdb_user}@localhost IDENTIFIED BY '${ci_jdb_password}'"
if [ $? -eq "0" ]
then
	echo "OK! Datenbankbenutzer erstellt."
else
	echo "FAIL! Datenbankbenutzer konnte nicht erstellt werden."
	exit 4
fi

echo "Datenbakrechte werden gesetzt..."
mysql -e "GRANT ALL PRIVILEGES ON ${ci_jdb_name}.* TO ${ci_jdb_user}@localhost"
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

build/scripts/shell/archiveworkspace.sh
build/scripts/shell/archiveextensions.sh
build/scripts/shell/archivebuildenviroment.sh

echo "OK! Erste Phase erfolgreich beendet."

exit 0
