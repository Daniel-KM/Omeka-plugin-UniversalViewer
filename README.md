Universal Viewer (plugin for Omeka)
==================================

[Universal Viewer] is a plugin for [Omeka] that adds the [IIIF] specifications
and the [UniversalViewer], a unified online player for any file. It can display
books, images, maps, audio, movies, pdf and anything else as long as the
appropriate extensions are installed. Rotation, zoom, inside search, etc. may be
managed too.

The full specification of the "International Image Interoperability Framework"
standard is supported (level 2), so any other widget that support it can use it.

The Universal Viewer was firstly developed for the [British Library] and the
[National Library of Wales], then open sourced (unlike the viewer of [Gallica],
the public digital library built by the [Bibliothèque Nationale de France],
which is sold to its partners). Contents are available in the [Wellcome Library].

See a [demo] on the [Bibliothèque patrimoniale] of [Mines ParisTech], or you can
set the url "https://patrimoine.mines-paristech.fr/items/presentation/2/manifest"
in the official [example server], because this is fully interoperable.


Installation
------------

Uncompress files and rename plugin folder "UniversalViewer".

Then uncompress and rename "uv" the last official release of the [UniversalViewer]
in the subdirectory `view/shared/javascripts` of the plugin. "nodejs" and other
packages are not needed, because only the viewer is used: the IIIF server is
provided directly by the plugin itself.

Then install it like any other Omeka plugin.

Options for the integration of the player can be changed in the config page.
Options for the UniversalViewer player can be changed in the json file
"config.json": copy and update it in a folder named "universal-viewer" in your
theme.
Options for the IIIF server can be changed in the helpers "IiifCollection.php",
"IiiifManifest.php" and "IiifInfo.php" of the plugin.

Usage
-----

The viewer is always available at `http://www.example.com/collections/play/{collection id}`
and `http://www.example.com/items/play/{item id}`. Furthermore, it is
automatically embedded in "collections/show/{id}" and "items/show/{id}" pages.
This can be disabled in the config of the plugin.

All routes for the player and the IIIF server are defined in the file "routes.ini".

To embed the BookReader with more control, three mechanisms are provided. So,
according to your needs, you may add this code in the `items/show.php` file of
your theme or anywhere else, as long a record is defined (as variable or as
current record'collection' or 'item').

* Hook

```
    <?php
    echo get_specific_plugin_hook_output('UniversalViewer', 'public_items_show', array(
        'record' => $item,
    ));
    ?>
```

* Helper

```
     echo $this->universalViewer();
```

* Shortcode
    - In a field that can be shortcoded: `[uv]`.

```
    <?php
        echo $this->shortcodes('[uv id=1]');
    ?>
```

 All mechanisms share the same arguments and all of them are optional. For the
 selection of the record, the order of priority is: "id", "record" / "type",
 "item", "collection", current record.

If collections are organized hierarchically with the plugin [CollectionTree], it
will be used to build manifests for collections.


Troubleshooting
---------------

See online issues on the [plugin issues] page on GitHub.


License
-------

This plugin is published under the [CeCILL v2.1] licence, compatible with
[GNU/GPL] and approved by [FSF] and [OSI].

In consideration of access to the source code and the rights to copy, modify and
redistribute granted by the license, users are provided only with a limited
warranty and the software's author, the holder of the economic rights, and the
successive licensors only have limited liability.

In this respect, the risks associated with loading, using, modifying and/or
developing or reproducing the software by the user are brought to the user's
attention, given its Free Software status, which may make it complicated to use,
with the result that its use is reserved for developers and experienced
professionals having in-depth computer knowledge. Users are therefore encouraged
to load and test the suitability of the software as regards their requirements
in conditions enabling the security of their systems and/or data to be ensured
and, more generally, to use and operate it in the same conditions of security.
This Agreement may be freely reproduced and published, provided it is not
altered, and that no provisions are either added or removed herefrom.

The [UniversalViewer] is published under the [MIT licence].


Contact
-------

See documentation on the UniversalViewer and the IIIF on their respective site.

Current maintainers of the plugin:
* Daniel Berthereau (see [Daniel-KM])

First version of this plugin has been built for [Mines ParisTech].


Copyright
---------

Widget [UniversalViewer]:

* Copyright Wellcome Library, 2013
* Copyright British Library, 2015
* Copyright National Library of Wales, 2015

Plugin Universal Viewer for Omeka:

* Copyright Daniel Berthereau, 2015


[Universal Viewer]: https://github.com/Daniel-KM/UniversalViewer4Omeka
[Omeka]: https://omeka.org
[IIIF]: http://iiif.io
[UniversalViewer]: https://github.com/UniversalViewer/universalviewer
[British Library]: http://bl.uk
[National Library of Wales]: http://www.llgc.org.uk
[Gallica]: http://gallica.bnf.fr
[Bibliothèque Nationale de France]: http://bnf.fr
[Wellcome Library]: http://wellcomelibrary.org
[demo]: https://patrimoine.mines-paristech.fr/collections/play/7
[Bibliothèque patrimoniale]: https://patrimoine.mines-paristech.fr
[Mines ParisTech]: http://mines-paristech.fr
[example server]: http://universalviewer.azurewebsites.net
[CollectionTree]: https://github.com/Daniel-KM/CollectionTree
[plugin issues]: https://github.com/Daniel-KM/UniversalViewer4Omeka/issues
[CeCILL v2.1]: https://www.cecill.info/licences/Licence_CeCILL_V2.1-en.html
[GNU/GPL]: https://www.gnu.org/licenses/gpl-3.0.html
[FSF]: https://www.fsf.org
[OSI]: http://opensource.org
[MIT licence]: https://github.com/UniversalViewer/universalviewer/blob/master/LICENSE.txt
[Daniel-KM]: https://github.com/Daniel-KM
