const {
	ClassicEditor,
	Autosave,
	Essentials,
	Paragraph,
	Bold,
	Italic,
	Link,
	AutoLink,
	Markdown,
	PasteFromMarkdownExperimental,
	Autoformat,
	ImageInline,
	ImageToolbar,
	ImageBlock,
	ImageTextAlternative,
	ImageCaption,
	ImageInsertViaUrl,
	AutoImage,
	Indent,
	IndentBlock,
	List,
	Heading,
	BlockQuote,
	HorizontalLine,
	Table,
	TableToolbar,
	TableCaption
} = window.CKEDITOR;

// Get license key from window variable (set by template) or use null for GPL
const LICENSE_KEY = window.CKEDITOR_LICENSE_KEY || null;

const editorConfig = {
	toolbar: {
		items: [
			'undo',
			'redo',
			'|',
			'heading',
			'|',
			'bold',
			'italic',
			'|',
			'horizontalLine',
			'link',
			'insertImageViaUrl',
			'insertTable',
			'blockQuote',
			'|',
			'bulletedList',
			'numberedList',
			'outdent',
			'indent'
		],
		shouldNotGroupWhenFull: false
	},
	plugins: [
		Autoformat,
		AutoImage,
		AutoLink,
		Autosave,
		BlockQuote,
		Bold,
		Essentials,
		Heading,
		HorizontalLine,
		ImageBlock,
		ImageCaption,
		ImageInline,
		ImageInsertViaUrl,
		ImageTextAlternative,
		ImageToolbar,
		Indent,
		IndentBlock,
		Italic,
		Link,
		List,
		Markdown,
		Paragraph,
		PasteFromMarkdownExperimental,
		Table,
		TableCaption,
		TableToolbar
	],
	heading: {
		options: [
			{
				model: 'paragraph',
				title: 'Paragraph',
				class: 'ck-heading_paragraph'
			},
			{
				model: 'heading1',
				view: 'h1',
				title: 'Heading 1',
				class: 'ck-heading_heading1'
			},
			{
				model: 'heading2',
				view: 'h2',
				title: 'Heading 2',
				class: 'ck-heading_heading2'
			},
			{
				model: 'heading3',
				view: 'h3',
				title: 'Heading 3',
				class: 'ck-heading_heading3'
			},
			{
				model: 'heading4',
				view: 'h4',
				title: 'Heading 4',
				class: 'ck-heading_heading4'
			},
			{
				model: 'heading5',
				view: 'h5',
				title: 'Heading 5',
				class: 'ck-heading_heading5'
			}
		]
	},
	image: {
		toolbar: ['toggleImageCaption', 'imageTextAlternative']
	},
	language: 'hu',
	licenseKey: LICENSE_KEY,
	link: {
		addTargetToExternalLinks: true,
		defaultProtocol: 'https://',
		decorators: {
			toggleDownloadable: {
				mode: 'manual',
				label: 'Downloadable',
				attributes: {
					download: 'file'
				}
			}
		}
	},
	placeholder: 'Type or paste your content here!',
	table: {
		contentToolbar: ['tableColumn', 'tableRow', 'mergeTableCells']
	}
};

// Function to create CKEditor instance with optional initial data
function createCKEditor(element, initialData = '') {
	const config = { ...editorConfig };
	if (initialData) {
		config.initialData = initialData;
	}
	return ClassicEditor.create(element, config);
}