# Website image sources and management

The Clovic logo is the original file supplied by the owner on 27 October 2026. The image is preserved unchanged; CSS frames the wordmark to hide the surrounding whitespace. Both header and footer use the same editable store setting.

Stock images were located through web image searches and downloaded from Pexels for demo styling. They are not represented as genuine Clovic product photographs. The original listing pages and license are retained below:

- Polo-shirt arrangement: onkar salvi, [Pexels photo 11100267](https://www.pexels.com/photo/close-up-shot-of-polo-shirts-on-blue-surface-11100267/).
- Green T-shirt on a hanger: Ron Lach, [Pexels photo 9594692](https://www.pexels.com/photo/fashion-shopping-indoors-no-people-9594692/).
- Shirt rack / homepage inspiration: Thirdman, [Pexels photo 8483478](https://www.pexels.com/photo/variety-of-clothes-hanging-on-a-rack-8483478/).
- Beige trousers: José Martin Segura Benites, [Pexels photo 27081006](https://www.pexels.com/photo/woman-wearing-beige-trousers-on-a-pavement-27081006/).
- [Pexels license](https://www.pexels.com/license/).

Images are served locally from the public storage disk, without third-party image requests. Each placement has a separate file, so removing a product image does not break the homepage or a category photo.

## Remove or replace photos

Sign in with an admin/owner account, then:

- `/admin/settings`: preview the current logo/homepage photo, check its Remove option and save. Uploading a replacement also removes the old file after the database commit.
- `/admin/content/categories`: open a category, check Remove current image and save, or upload its replacement.
- `/admin/products`: edit a demo product, select Delete image beside its photo, then Save product. Each product has its own copy.

Removed photos fall back to the existing artwork/text branding where appropriate. Removing a photo does not delete a product or change its price, stock, or order history. The import was a one-time local action; deleting photos will not trigger automatic re-downloads.

The logo/photos and their database paths are user media, not hardcoded template assets. Include `storage/app/public` and the database in backups/deployment, as described in DEPLOYMENT.md. There are no default admin credentials; use the owner setup command in README.md if an owner account has not yet been established.
