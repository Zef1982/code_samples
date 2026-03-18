document.addEventListener("DOMContentLoaded", () => {
	// Remove useless 'Make selection below' options.
	document.querySelector('select#color option').remove();
	document.querySelector('select#size option').remove();
});

// Prevent slider from resetting on variation change!
window.wpgs_js_data.gallery_count=0;

let observer = new MutationObserver(
	mutationList => {
		// Things have changed inside the variations form!
		let iterator = 0;
		for(let i=0; i<mutationList.length; i++){
			let newValue = mutationList[i].target.getAttribute(mutationList[i].attributeName);
			// We start when the loader is gone!
			if(mutationList[i].target.classList == 'blockUI blockOverlay' && newValue.indexOf('display: none') >= 0){
				// Apparently this happens a couple of times in the process...
				iterator++;
				if(iterator == 2){
					slideToSelectedColor();
				}
			}
		}
});

// Slide to the selected color. Initially or when option is selected manually!
function slideToSelectedColor() {

	let selectedColor = jQuery('select#color').val().replaceAll(" ","_").toLowerCase();
	let slides = jQuery('.wpgs-nav .slick-track .slick-slide').not('.slick-cloned');
	
    // Loop the slides to match the image sources with the selected color.
	slides.each(function(index){
		let  imgSrc = jQuery(this).find('img').attr('src');
		let selectedColorMatchesImgSrc = imgSrc.indexOf('t_' + selectedColor) >= 0;
		let currentSlideIndex = jQuery('.wpgs-for').slick('slickCurrentSlide');
		
        if(selectedColorMatchesImgSrc && parseInt(index) !== parseInt(currentSlideIndex)) {
			// Slide to the selected color.
			jQuery('.wpgs-for').slick('slickGoTo', parseInt(index));
			return false;
		}
	});
	// Done selecting color manually!
	selectedColor = false;
}

// Automatically select the color on slide change.
jQuery('.wpgs-for').on('afterChange', function(event, slick, currentSlide, nextSlide){
	
	let slide = jQuery('.slick-track .slick-slide:nth-child(' + (currentSlide + 1) + ')');
	let imgSrc = slide.find('img').attr('src');
	let colorOptions = jQuery('select#color option');

	// Loop through the select options to match the color with the current slide image source.
	colorOptions.each(function(index){
		
        let color = jQuery(this).val().replaceAll(" ","_").toLowerCase();
		let colorMatchesImage = imgSrc.indexOf('t_' + color) >= 0;
		var selected = jQuery(this).attr('selected');
		
        // Select the matching option, deselct all other options!
		if(colorMatchesImage && selected !== 'undefined' && selected !== 'false') {
			
            jQuery(this).attr('selected', 'selected');
			
            // Trigger change in WC!
			jQuery(this).trigger('change');

		}else{
			jQuery(this).removeAttr('selected');
		}
	});
});

// Observe the variations form to check if it's  done loading...
let variationsForm = document.getElementsByClassName('variations_form')[0];
observer.observe(variationsForm, {
	childList: true, 
	subtree: true, 
	attributes: true,
	attributeOldValue: true
});