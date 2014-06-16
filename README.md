#WeRelate

Source code for the WeRelate.org wiki

To install:
* download a skeleton wiki database from http://public.werelate.org.s3.amazonaws.com/wikidb.sql
* create a database in mysql and import the skeleton

Simple setup:
  * copy htaccess.sample to .htaccess and edit the environment variables therein

Full set-up:
  * copy the files in this project to a *w* directory under your htdocs root
  * create an apache site file from conf/apache2.sample and copy it to your apache2/sites-available directory as *sitename*
    * you will need to fill in or modify the values in apache2.sample based upon your environment
  * enable the site; e.g., `a2ensite` *sitename*

More:
* If you want to search, you'll need the search project
* If you want to process gedcoms, you'll need the werelate-gedcom and gedcom-review projects
* The wikidata project contains various scripts for parsing and batch-updating pages
* The Family Tree Explorer is being deprecated; the source code is not available
