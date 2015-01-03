shotwell-merge
==============

POC for merging Shotwell databases and thumbnails.


This is a proof of concept which worked really well for me.

I had two instances of Shotwell - one on my old laptop and one on my older laptop.
Altogether around 15k pictures, all tagged and categorized. As you can imagine,
I have spent long time managing the whole lot and did not really fancy doing that again.

Since Shotwell does not provide any tool for merging the database and I could not find any either,
I have decided to spend a day writing something mys1elf. I have worked with SQLite in PHP before
and because I wanted this done quickly, I rolled with it. Otherwise I would have choosen node :)

Ok, so what's the dealio?

The script does three things:

* copies all records from the source database to the destination database
* updates referencses - as you can imagine, the ids are different after copying, so we need to deal with it
* renames thumbnails - the names are merely hex representation of the photo id with 'thumb' prefix and padded to 16 characters

How to merge the databases?

It's easy-peasy. I presume you have two instances of Shotwell. Take the db from instance #1 and copy it to ./results/photo.db
Then run the script pointing it to the instance#2. You can also point it to the thumbnails directory if you want
the renaming done for you as well. Might be quite handy, because Shotwell takes quite to create the thumbnails
if you have a few thousands of pictures.

The Shotwell db is usually found in:
` ~/.local/share/shotwell/data/photo.db`

The Shotwell thumbs are usually here:
`~/.cache/shotwell/thumbs/`

Have fun!
Jacek
