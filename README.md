Universal Viewer (plugin for Omeka)
===================================

[Universal Viewer] is a plugin for [Omeka] that adds the [IIIF] specifications
in order to serve images like an [IIPImage] server, and the [UniversalViewer], a
unified online player for any file. It can display books, images, maps, audio,
movies, pdf, 3D, and anything else as long as the appropriate extension is
installed. Rotation, zoom, inside search, etc. may be managed too.

The full specification of the "International Image Interoperability Framework"
standard is supported (level 2), so any other widget that supports it can use it.

The Universal Viewer was firstly developed by [Digirati] for the [Wellcome Library]
of the [British Library] and the [National Library of Wales], then open sourced
(unlike the viewer of [Gallica], the public digital library built by the [Bibliothèque Nationale de France],
which is sold to its partners).

See a [demo] on the [Bibliothèque patrimoniale] of [Mines ParisTech], or you can
set the url "https://patrimoine.mines-paristech.fr/collections/presentation/7/manifest"
in the official [example server], because this is fully interoperable.


Installation
------------

Uncompress files and rename plugin folder "UniversalViewer".

Then install it like any other Omeka plugin.

Some options can be set:
- Options for the integration of the player can be changed in the config page.
- Options for the UniversalViewer player can be changed in the json file
  "config.json": copy and update it in a folder named "universal-viewer" inside
  the folder of the theme.
- To use an alternative config for some items, add an option `config` with
  its url in the array of arguments passed to the viewer (see below).
- Options for the IIIF server can be changed in the helpers "IiifCollection.php",
  "IiifManifest.php" and "IiifInfo.php" of the plugin.

See below the notes for more info.

* Javascript library "UniversalViewer"

Since version 2.2.1, the distribution release of the javascript library [UniversalViewer]
is included in the folder `views/shared/javascripts/uv/`. If you want a more
recent release, clone the last [distribution] in the same directory. "nodejs",
other packages and any other files are not needed, because only the viewer is
used: the IIIF server is provided directly by the plugin itself.

* Processing of images

Images are transformed internally via the GD or the ImageMagick libraries. GD is
generally a little quicker, but ImageMagick manages many more formats. An option
allows to select the library to use according to your server and your documents.
So at least one of the php libraries ("php-gd" and "php-imagick" on Debian)
should be installed.

* Display of big images

If your images are big (more than 10 to 50 MB, according to your server and your
public), it's highly recommended to tile them with a plugin such [OpenLayersZoom].
Then, tiles will be automatically displayed by Universal Viewer.

* Adaptation of the config

To customize the configuration of the plugin, create a directory `universal-folder`
in your theme and copy the file `plugins/UniversalViewer/views/public/universal-viewer/config.json`
inside it: `themes/My_Theme/universal-viewer/config.json`.

Details of the config options can be found on the [wiki] and tested [online].


Usage
-----

The viewer is always available at `http://www.example.com/collections/play/{collection id}`
and `http://www.example.com/items/play/{item id}`. Furthermore, it is
automatically embedded in "collections/show/{id}" and "items/show/{id}" pages.
This can be disabled in the config of the plugin.

All routes for the player and the IIIF server are defined in the file "routes.ini".

To embed the Universal Viewer with more control, three mechanisms are provided.
So, according to your needs, you may add this code in the `items/show.php` file
of your theme or anywhere else, as long a record is defined (as variable or as
current record'collection' or 'item').

* Helper (recommended)

```php
    // Display the viewer with the current record and default parameters.
    echo $this->universalViewer();

    // Display the viewer with the specified item and specified config.
    echo $this->universalViewer(array(
        'item' => $item,
        'config' => 'https://example.com/my/specific/config.json',
    ));
```

* Shortcode

  - In a field that can be shortcoded: `[uv]`.
  - In the theme:

```php
    echo $this->shortcodes('[uv record=1 type=collection]');
```

* Hook

```php
    echo get_specific_plugin_hook_output('UniversalViewer', 'public_items_show', array(
        'record' => $item,
        'view' => $this,
    ));
```

All mechanisms share the same arguments and all of them are optional. For the
selection of the record, the order of priority is: "id", "record" / "type",
"item", "collection", current record.

If collections are organized hierarchically with the plugin [CollectionTree], it
will be used to build manifests for collections.


Notes
-----

- A batch edit is provided to sort images before other files (pdf, xml...) that
are associated to an item (Items > check box items > edit button).
- The plugin works fine for a standard usage, but the images server may be
improved for requests made outside of the Universal Viewer when OpenLayersZoom
is used. Without it, a configurable limit should be set (10 MB by default).
- If an item has no file, the viewer is not able to display it, so a check is
automatically done.
- Media: Currently, no image should be available in the same item.
- Audio/Video: the format should be supported by the browser of the user. In
fact, only open, free and/or common codecs are really supported: "mp3" and "ogg"
for audio and "webm" and "ogv" for video. They can be modified in the file
"routes.ini".

*Warning*

PHP should be installed with the extension "exif" in order to get the size of
images. This is the case for all major distributions and providers.

If technical metadata are missing for some images, in particular when the
extension "exif" is not installed or when images are not fully compliant with
the standards, they should be rebuilt. A notice is added in the error log.
A form in the batch edit can be used to process them automatically: check the
items in the "admin/items/browse" view, then click the button "Edit", then the
checkbox "Rebuild metadata when missing". The viewer will work without these
metadata, but the display will be slower.


3D models
---------

The display of 3D models is fully supported by the widget and natively managed
since the release 2.3. 3D models are managed via the [threejs] library.

* Possible requirement

The plugin [Archive Repertory] must be installed when the json files that
represent the 3D models use files that are identified by a basename and not a
full url. This is generally the case, because the model contains an external
image for texture. Like Omeka hashes filenames when it ingests files, the file
can't be retrieved by the Universal Viewer.

This plugin is not required when there is no external images or when these
images are referenced in the json files with a full url.

* Example

  - Install the plugin [Archive Repertory].
  - Download the next three files from the official examples:
    - http://files.universalviewer.io/manifests/foundobjects/thekiss/thumb.jpg
    - http://files.universalviewer.io/manifests/foundobjects/thekiss/thekiss.jpg
    - http://files.universalviewer.io/manifests/foundobjects/thekiss/thekiss.json
  - Add a new item with these three files, in this order, and the following
  metadata:
    - Title: The Kiss
    - Date: 2015/11/27
    - Description: Soap stone statuette of Rodin's The Kiss. Found at Snooper's Paradise in Brighton UK.
    - Rights: 3D model produced by Sophie Dixon
    - LIcense (or Rights): by-nc-nd
  - Go to the public page of the item and watch it!

*Important*: When using [Archive Repertory] and when two files have the same
base name (here "thekiss.jpg" and "thekiss.json"), the image, that is referenced
inside the json, must be uploaded before the json.
Furthermore, the name of the thumbnail must be `thumb.jpg` and it is recommended
to upload it first.

Finally, note that 3D models are often heavy, so the user has to wait some
seconds that the browser loads all files and prepares them to be displayed.


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
* Copyright British Library, 2015-2016
* Copyright National Library of Wales, 2015-2016
* Copyright [Edward Silverton] 2013-2016

Plugin Universal Viewer for Omeka:

* Copyright Daniel Berthereau, 2015-2016


[Universal Viewer]: https://github.com/Daniel-KM/UniversalViewer4Omeka
[Omeka]: https://omeka.org
[IIIF]: http://iiif.io
[IIPImage]: http://iipimage.sourceforge.net
[UniversalViewer]: https://github.com/UniversalViewer/universalviewer
[Digirati]: http://digirati.co.uk
[British Library]: http://bl.uk
[National Library of Wales]: http://www.llgc.org.uk
[Gallica]: http://gallica.bnf.fr
[Bibliothèque Nationale de France]: http://bnf.fr
[Wellcome Library]: http://wellcomelibrary.org
[demo]: https://patrimoine.mines-paristech.fr/collections/play/7
[Bibliothèque patrimoniale]: https://patrimoine.mines-paristech.fr
[Mines ParisTech]: http://mines-paristech.fr
[example server]: http://universalviewer.io/examples/
[wiki]: https://github.com/UniversalViewer/universalviewer/wiki/Configuration
[online]: http://universalviewer.io/examples/
[official release]: https://github.com/UniversalViewer/universalviewer/releases
[distribution]: https://github.com/UniversalViewer/universalviewer/tree/master/dist
[OpenLayersZoom]: https://github.com/Daniel-KM/OpenLayersZoom
[CollectionTree]: https://github.com/Daniel-KM/CollectionTree
[threejs]: https://threejs.org
[Archive Repertory]: https://omeka.org/add-ons/plugins/archive-repertory
[plugin issues]: https://github.com/Daniel-KM/UniversalViewer4Omeka/issues
[CeCILL v2.1]: https://www.cecill.info/licences/Licence_CeCILL_V2.1-en.html
[GNU/GPL]: https://www.gnu.org/licenses/gpl-3.0.html
[FSF]: https://www.fsf.org
[OSI]: http://opensource.org
[MIT licence]: https://github.com/UniversalViewer/universalviewer/blob/master/LICENSE.txt
[Edward Silverton]: https://github.com/edsilv
[Daniel-KM]: https://github.com/Daniel-KM "Daniel Berthereau"
