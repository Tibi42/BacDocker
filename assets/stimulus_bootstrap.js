import { startStimulusApp } from '@symfony/stimulus-bundle';
import ArticleFormController from './controllers/article_form_controller.js';
import CarouselPreviewController from './controllers/carousel_preview_controller.js';
import SearchAutocompleteController from './controllers/search_autocomplete_controller.js';

const app = startStimulusApp();
app.register('article-form', ArticleFormController);
app.register('carousel-preview', CarouselPreviewController);
app.register('search-autocomplete', SearchAutocompleteController);
// register any custom, 3rd party controllers here
// app.register('some_controller_name', SomeImportedController);
