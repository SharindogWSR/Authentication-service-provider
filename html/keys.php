<?php
        $privateRaw = openssl_pkey_new();
        $public = openssl_pkey_get_details($privateRaw)['key'];
        $private = '';
        openssl_pkey_export($privateRaw, $private);

        print('<pre>');
        print_r([
                'private' => $private,
                'public' => $public
        ]);
        print('</pre>');
