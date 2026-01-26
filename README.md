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

## DELETE WATCHLIST

1. comment out the auto-run of the indexer every 5 minutes

```
sudo vim /etc/crontab
```

2. update the indexer properties to focus on indexing updates quickly

```
vim ~/search/conf/index.properties

max_index_seconds=3600
foreground_delay_millis=20
background_delay_millis=200
max_background_pages=0
max_index_task_size=50000
index_batch_size=100
```

3. add pages to the index queue 

```
INSERT INTO index_request (ir_page_id, ir_timestamp)
SELECT page_id, CONVERT(DATE_FORMAT(NOW(), '%Y%m%d%H%i%s') USING latin1)
FROM watchlist
JOIN page ON page_namespace = (wl_namespace & ~1) AND page_title = wl_title
WHERE wl_user = <user-id>
AND (wl_namespace & 1) = 0
AND NOT EXISTS (
  SELECT 1 
  FROM familytree_page
  WHERE fp_namespace = (wl_namespace & ~1)
  AND fp_title = wl_title
  AND fp_user_id = wl_user
); 
```

4. delete pages from the watchlist

```
DELETE FROM watchlist
WHERE wl_user = <user-id>
AND NOT EXISTS (
  SELECT 1
  FROM familytree_page
  WHERE fp_namespace = (wl_namespace & ~1)
  AND fp_title = wl_title
  AND fp_user_id = wl_user
); 
```

5. manually run the indexer - do this one or more times until it completes within a few minutes

```
scripts/jobs/run-indexer.sh
```

6. upload the index

```
scripts/jobs/upload-index.sh
```

7. put the indexer properties back the way they were

```
vim ~/search/conf/index.properties

max_index_seconds=280
foreground_delay_millis=100
background_delay_millis=200
max_background_pages=200
max_index_task_size=5000
index_batch_size=50
```

8. uncomment the auto-run of the indexer every 5 minutes

```
sudo vim /etc/crontab
```

