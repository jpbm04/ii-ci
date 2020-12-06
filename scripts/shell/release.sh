#!/bin/bash

mkdir release

pwdbak=$(pwd)

IFS='/' read -ra REPO <<< "$GITHUB_REPOSITORY"
REPO_USER="${REPO[0]}"
REPO_NAME="${REPO[1]}"

echo "Creating \"${REPO_NAME}\" release..."
cd extensions/${REPO_NAME}/${REPO_NAME}
sed -i "s/<\/server>/https:\/\/${REPO_USER}.github.io\/${REPO_NAME}\/updates.xml<\/server>/" ${REPO_NAME}.xml
zip -r ../../../release/${REPO_NAME} *
cd ${pwdbak}
echo "${REPO_NAME} release succesfully created."

echo "Updating update XML for \"${REPO_NAME}\"..."
echo -ne "<updates>\n\t<update>\n\t\t<name>THM Organizer</name>\n\t\t<element>${REPO_NAME}</element>\n\t\t<type>component</type>\n\t\t<version>${GITHUB_REF:1}</version>\n\t\t<client>administrator</client>\n\t\t<infourl title=\"MNI Homepage\">https://www.thm.de/mni/</infourl>\n\t\t<targetplatform name=\"joomla\" version=\"3.*\"/>\n\t\t<maintainer>THM iCampus</maintainer>\n\t\t<maintainerurl>https://www.thm.de/</maintainerurl>\n\t\t<tags>\n\t\t\t<tag>staging</tag>\n\t\t</tags>\n\t\t<downloads>\n\t\t\t<downloadurl type=\"full\" format=\"zip\">\n\t\t\t\thttps://github.com/${REPO_USER}/${REPO_NAME}/releases/download/${GITHUB_REF}/${REPO_NAME}.zip\n\t\t\t</downloadurl>\n\t\t</downloads>\n\t</update>\n</updates>" > pages/${REPO_NAME}/updates.xml
echo "Update XML updated."


exit 0
