# 
# Génération du manuel utilisateur à partir
# du source docbook
#

SOURCE	   = galette
TARGET     = manual
HTMLDIR    = ./

# Path à changer selon configuration locale
XSLDIR     = /usr/share/xml/docbook/stylesheet/nwalsh

XMLFILE     = ${SOURCE}.xml
HTMLFILE    = ${HTMLDIR}${TARGET}.html
FOFILE      = ${TARGET}.fo
PDFFILE     = ${TARGET}.pdf

FOSTYLESHEET      = ${XSLDIR}/fo/docbook.xsl
HTMLSTYLESHEET    = ${SOURCE}.xsl

all:	html pdf

${HTMLFILE}:  ${XMLFILE}
	xsltproc -o $@ ${HTMLSTYLESHEET} $<

${PDFFILE}:  ${XMLFILE}
	xsltproc -o ${FOFILE} ${FOSTYLESHEET} $<
	Fop -fo ${FOFILE} $@

html:	${XMLFILE}

pdf:	${XMLFILE}

clean:
	rm -f ${HTMLFILE} ${PDFFILE}

