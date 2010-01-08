=============================
TwIRCd - A Twitter IRC server
=============================

TwIRCd proxies Twitter through an IRC server, so that you can read, search and
update Twitter using your IRC client. It is written for PHP 5.3 using an
extensible architecture and does not require anything but the PHP socket
extension.

Usage
=====

Check out the repository, edit the ``server.php`` file, since you might want to
change the IP and port TwIRCd listens on, and start the server using ``php
server.php``. Since this software is in an early stage it will currently log a
lot to STDOUT. You probably just want to start it in ``screen``, or change the
Log level, like this::

    $logger = new Logger\StdOut( E_WARNING | E_ERROR );

Now you can connect using any IRC client (or through a proxy like BIP__) to the
IRC server using your twitter credentials.

You will automatically join a channel called "&twitter" where all your friends
should be listed as channel members. Your home timeline (the new friends
timeline) will be posted into that channel. Everything you write into this
channel will be posted back to twitter.

Direct messages
---------------

If someone writes you a direct message, it will pop up as a query. Everything
you answer into that query will be sent back as a direct message to the user.

Searches
--------

You can join any channel, like "#php" and this will add a new search. By
default it will search for the channel-name as a hash-tag. To change the exact
search phrase update the topic of the channel. The following command will
change the search to "#php OR #phpug", for example::

    /TOPIC #php OR #phpug

Rate limit
----------

To not hit the rate limit, TwIRCd automatically adapts the request rate
depending on the rate limit twitter reports in its HTTP responses. Therefore
the time between fetching new massages might vary slightly.

__ http://bip.t1r.net/


..
   Local Variables:
   mode: rst
   fill-column: 79
   End: 
   vim: et syn=rst tw=79