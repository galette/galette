#!/usr/bin/python
# -*- coding: utf-8 -*-
"""
Copyright © 2004-2013 The Galette Team

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

@author Georges Khaznadar <unknown@unknow.com>
@author Johan Cwiklinski <johan@x-tnd.be>
"""
import re

def seekfuzzy(s,cursor):
    """ deals with the translations in the string list s, after the
    cursor-th line, which can be used for future fuzzy translations.
    Such translations are prefixed by #~
    Returns a boolean found, the cursor value and a tuple (msgid,msgstr)"""
    
    id_re=re.compile('#~ msgid "(.*)"')
    str_re=re.compile('#~ msgstr "(.*)"')
    suite_re=re.compile('#~ "(.*)"')
    found=0
    msgid=''
    msgstr=''

    while(cursor < len(s) and not id_re.match(s[cursor])):
        cursor=cursor+1
    if (cursor < len(s)):
        found=1
        msgid=id_re.match(s[cursor]).group(1)
        cursor=cursor+1
        while(cursor < len(s) and suite_re.match(s[cursor])):
            msgid=msgid+suite_re.match(s[cursor]).group(1)
            cursor=cursor+1
    while(cursor < len(s) and not str_re.match(s[cursor])):
        cursor=cursor+1
    if (cursor < len(s)):
        msgstr=str_re.match(s[cursor]).group(1)
        cursor=cursor+1
        while(cursor < len(s) and suite_re.match(s[cursor])):
            msgstr=msgstr+suite_re.match(s[cursor]).group(1)
            cursor=cursor+1
    return (found, cursor, (msgid,msgstr))

class translation_task:
    """This class deals with sources files concerned by a msgid and a msgstr"""
    def __init__(self):
        self.files=[]
        self.msgid=''
        self.msgstr=''
        return
    def appendfiles(self,files):
        """appends a list of files to self"""
        
        for f in files:
            self.files.append(f)
        return self.files
        
    def seekmsgid(self,s,cursor):
        """Eats up the next msgid in the lines list s, after the
        cursor-th line. calls seekmsgstr, and
        returns the value of cursor"""
        
        id_re=re.compile('msgid "(.*)"')
        str_re=re.compile('msgstr "(.*)"')
        suite_re=re.compile('"(.*)"')
        while (cursor < len(s) and not id_re.match(s[cursor])):
            cursor=cursor+1
        if (id_re.match(s[cursor])):
            self.msgid=id_re.match(s[cursor]).group(1)
            cursor=cursor+1
        while (cursor < len(s) and not str_re.match(s[cursor])):
            self.msgid=self.msgid+suite_re.match(s[cursor]).group(1)
            cursor=cursor+1
        return self.seekmsgstr(s,cursor)
    
    def seekmsgstr(self,s,cursor):
        """Eats up the next msgstr in the lines list s, after the
        cursor-th line. 
        returns the value of cursor"""

        suite_re=re.compile('"(.*)"')
        str_re=re.compile('msgstr "(.*)"')
        while (cursor < len(s) and not str_re.match(s[cursor])):
            cursor=cursor+1
        if (str_re.match(s[cursor])):
            self.msgstr=str_re.match(s[cursor]).group(1)
            cursor=cursor+1
        while (cursor < len(s) and suite_re.match(s[cursor])):
            self.msgstr=self.msgstr+suite_re.match(s[cursor]).group(1)
            cursor=cursor+1
        return cursor
        

def next_translation (s, cursor):
    """Browses the lines list s, after the cursor-th line, and
    builds a translation_task object task.
    returns a boolean found, the value of cursor and task."""
    
    file_re=re.compile('#:(.*)')
    str_re=re.compile('msgstr (.*)')

    task=translation_task()
    while (cursor < len(s)):
        if (file_re.match(s[cursor])) :
            while (file_re.match(s[cursor])) :
                task.appendfiles(s[cursor].split()[1:])
                cursor=cursor+1
            cursor=task.seekmsgid(s,cursor)
            return (1,cursor,task)
        cursor = cursor+1
    return (0,cursor,task)


class tt_dic :
    """ dictionnary of translation tasks, indexed by msgid """
    
    def read_translations(self,pofilename):
        """will read all the translations (not the fuzzy repository)
        from the pofile named pofilename"""

        pofile=open(pofilename,'r')
        s=pofile.readlines()
        pofile.close()

        tts={}
        (found,cursor, tt) = next_translation (s,0)
        key=tt.msgid
        tts[key]=tt
        while (found) :
            (found,cursor, tt) = next_translation (s,cursor)
            if (len(tt.files)>0):
                key=tt.msgid
                tts[key]=tt
        return tts

    def source_files(self,tts):
        """ retrieves source files, numlines and replacement expressions from
        translation tasks in self.sources """

        sources={}
        for msgid in tts.keys():
            tt=tts[msgid]
            for file_num in tt.files:
                (file,num)=file_num.split(':')
                if (not file in sources.keys()):
                    sources[file]=[]
                sources[file].append((num,tt.msgid,tt.msgstr))
        return sources

    def translate_sources(self):
        """ makes source in the developmnent tree translated,
        replacing msgids within _() by their msgstr. Outputs
        the modified file near the original file named like it
        plus the suffix .new """
        
        for file in self.sources.keys():
            infile=open(file,'r')
            s=infile.readlines()
            infile.close
            for t in self.sources[file]:
                num=int(t[0])
                s[num-1]= s[num-1].replace(t[1],t[2]);
            outfile=open(file+".new",'w')
            outfile.writelines(s)
            outfile.close
            
    def fuzzy(self,pofilename):
        """registers fuzzy resources from the file named pofilename"""
        resource={}
        pofile=open(pofilename,'r')
        s=pofile.readlines()
        pofile.close()

        cursor=0
        while (cursor < len(s)):
            (found, cursor, fuzzy)=seekfuzzy(s,cursor)
            if (found==1):
                resource[fuzzy[0]]=fuzzy
        return resource

    def read_header(self,pofilename):
        """ reads the header of the file named pofilename """

        pofile=open(pofilename,'r')
        s=pofile.readlines()
        pofile.close()

        entete=''
        cursor=0
        while (cursor<len(s) and s[cursor][0:2] != '#:'):
            entete=entete+s[cursor]
            cursor=cursor+1
        return entete

    def translate_tt(self, ttdic):
        """ translates a dictionnary of translation tasks ttdic
        replacing its msgids by self's msgids
        returns a new dictionnary of translation tasks"""
        
        result = tt_dic()
        result.entete=ttdic.entete
        for msgid in self.tts.keys():
            newid=self.tts[msgid].msgstr
            result.tts[newid]= translation_task()
            result.tts[newid].appendfiles(self.tts[msgid].files)
            result.tts[newid].msgid=newid
            result.tts[newid].msgstr=ttdic.tts[msgid].msgstr
        result.sources=result.source_files(result.tts)
        for msgid in self.f_resource.keys() :
            newid=self.f_resource[msgid][1]
            if (msgid in ttdic.f_resource.keys()):
                result.f_resource[newid] = (newid,ttdic.f_resource[msgid][1])
        return result

    def display(self):
        """Outputs self in readable form """
        
        print self.entete
        for msgid in self.tts.keys():
            tt=self.tts[msgid]
            for f in tt.files:
                print "#:",f
            print 'msgid "'+msgid+'"'
            print 'msgstr "'+tt.msgstr+'"'
            print
        for msgid in self.f_resource.keys():
            t=self.f_resource[msgid]
            print '#~ msgid "'+msgid+'"'
            print '#~ msgstr "'+t[1]+'"'
            print
            
    def write_pofile(self, filename):
        """ Outputs a pofile based on self's contents, named filename """
        
        outfile=open(filename,'w')
        outfile.write(self.entete);
        for msgid in self.tts.keys():
            tt=self.tts[msgid]
            for f in tt.files:
                outfile.write("#: "+f+'\n')
            outfile.write('msgid "'+msgid+'"\n')
            outfile.write('msgstr "'+tt.msgstr+'"\n')
            outfile.write('\n')
        for msgid in self.f_resource.keys():
            t=self.f_resource[msgid]
            outfile.write('#~ msgid "'+msgid+'"\n')
            outfile.write('#~ msgstr "'+t[1]+'"\n')
            outfile.write('\n')
        outfile.close()
        
    def write_langfile(self, filename):
        """ Outputs a langfile in PHP syntax, based on self's contents,
        named filename """
        
        outfile=open(filename,'w')
        import time
        date=time.asctime(time.gmtime())+' (GMT)'
        outfile.write("<?php\n")
        outfile.write("// This file was automatically generated on "+date+"\n")
        outfile.write("// Don't modify it by hand, rather use the target lang from the Makefile.\n\n\n")
        for msgid in self.tts.keys():
            tt=self.tts[msgid]
            outfile.write("// ")
            for file in tt.files:
                outfile.write(file+" ")
            outfile.write("\n")
            """ Stripped quotes """
            outfile.write("$lang['"+msgid.replace("'","\\'")+"'] = '"+\
                          tt.msgstr.replace("'","\\'")+"';\n\n")
        outfile.write("?>")
        outfile.close()
        
    def __init__ (self, filename=''):
        if (len(filename)==0):
            self.tts={}
            self.sources={}
            self.f_resource={}
            self.entete=''
        else:
            self.tts=self.read_translations(filename)
            self.sources=self.source_files(self.tts)
            self.f_resource=self.fuzzy(filename)
            self.entete=self.read_header(filename)


ttdic_en=tt_dic('en_US.po')
import sys

if (len(sys.argv) < 3):
    print "Usage : make_lang_l12n.py po_filename lang_filename"
    print "  outputs translations (PHP syntax) to the file lang_filename"
else:
    pofile=sys.argv[1]
    ttdic=tt_dic(pofile)
    langfile=sys.argv[2]
    ttdic.write_langfile(langfile)
    
