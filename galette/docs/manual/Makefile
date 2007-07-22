# 
# Génération du manuel utilisateur à partir
# du source docbook
#

SOURCES	   = galette.xml \
maj.xml \
pref.xml \
quickinstall.xml \
unixinstall.xml \
user.xml webinstall.xml \
wininstall.xml

TARGET     = manual
HTMLDIR    = ./

# Path à changer selon configuration locale
XSLDIR     = /usr/share/xml/docbook/stylesheet/nwalsh

XMLFILE     = galette.xml
HTMLFILE    = ${HTMLDIR}${TARGET}.html
FOFILE      = ${TARGET}.fo
PDFFILE     = ${TARGET}.pdf

FOSTYLESHEET      = ${XSLDIR}/fo/docbook.xsl
HTMLSTYLESHEET    = ${SOURCE}.xsl

all:	${HTMLFILE} ${PDFFILE}

${HTMLFILE}:	${SOURCES}
	xsltproc -o $@ ${HTMLSTYLESHEET} $<

${PDFFILE}:	${SOURCES}
	xsltproc -o ${FOFILE} --stringparam fop.extensions 1 ${FOSTYLESHEET} ${XMLFILE}
	fop -fo ${FOFILE} ${PDFFILE}
#	Alternate command with fop only using xalan for fo transformation
#	fop -xml ${XMLFILE} -xsl ${FOSTYLESHEET} -pdf ${PDFFILE}

clean:
	rm -f ${HTMLFILE} ${PDFFILE}

