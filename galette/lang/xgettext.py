#!/usr/bin/python
# -*- coding: utf-8 -*-
"""
- Replace xgettext -k_T -n
- support string like _T("xxx") and {_T string("xxxx")}
- generates message.po with the same symtax as regular xgettext
- translatable string sort may differ from regular xgettext

Copyright © 2005-2016 The Galette Team

This file is part of Galette (http://galette.tuxfamily.org).

Galette is free software: you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation, either version 3 of the License, or
(at your option) any later version.

Galette is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with Galette. If not, see <http://www.gnu.org/licenses/>.

@author Didier Chevalier <unknown@unknow.com>
@author Johan Cwiklinski <johan@x-tnd.be>
"""
import sys
import re

# pattern definition
translatable        = re.compile("_(T|_)\((\"[^\"]*\")(, \"([^\"]*)\")?\)")
#same, with single quotes...
translatable_single = re.compile("_(T|_)\(('[^']*')(, '([^']*)')?\)")
tpl_translatable    = re.compile("_(T|_)\ string=(\"[^\"]*\")( domain=\"([^\"]*)\")?")

# constants string
startLoc = "#: "
nextLoc  = " "

#
domains = {}
dico = {}

def location():
    """
    String location in file
    """
    return inputFileName + ":" + str(lineNum+1)

def handleMatches(matches, repl_quotes=False):
    for match in matches:
        trans = match[1]
        #handle single quotes
        if repl_quotes == True:
            trans = '"%s"' % trans[1:-1]

        #define domain
        cur_domain = 'galette'
        if match[3] != '':
            cur_domain = match[3]

        if cur_domain not in domains:
            domains[cur_domain] = {}

        if trans in domains[cur_domain]:
            if domains[cur_domain][trans][-1:] == "\n":
                domains[cur_domain][trans] += startLoc + location()
            else:
                domains[cur_domain][trans] += nextLoc + location() + "\n"
        else:
            domains[cur_domain][trans] = startLoc + location()

#
for inputFileName in sys.argv[1:]:
    inFile = open(inputFileName)
    lines = inFile.readlines()
    inFile.close()
    # get line
    for lineNum, line in enumerate(lines):
        # search translatable strings
        matches = translatable.findall(line)
        handleMatches(matches)
        matches = translatable_single.findall(line)
        handleMatches(matches, True)
        matches = tpl_translatable.findall(line)
        handleMatches(matches)

for domain, strings in domains.items():
    outFile = open("%s.pot" % domain, 'w')
    for k, v in strings.items():
        outFile.write(v)
        if v[-1:] != "\n":
            outFile.write("\n")
        outFile.write("msgid " + k + "\nmsgstr \"\"\n\n")
    outFile.close()
