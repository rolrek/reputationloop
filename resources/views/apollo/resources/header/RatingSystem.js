var RatingSystem = function(businessRating,customerReviews){
	//Global Reference
	var self = this;
	//Constructor
	self.constructor = function(){
		//Render the businessRating
		self.renderRating("#business_rating",businessRating);
		//Render Customer Reviews
		self.renderCustomerReviews(customerReviews);
	};
	//Render Customer Reviews
	self.renderCustomerReviews = function(customerReviews){
		//Make sure we've got something...
		if(customerReviews){
			//Loop
			$.each(customerReviews,function(i,currentRating){
				console.log(currentRating);
			});
		}
		//Change le numerical ratings into star ratings
		$("#reviews .rating").each(function(i,customerRatingElement){
			self.renderRating(customerRatingElement,parseInt($(customerRatingElement).text()));
		});
		//Initialize Pagination
		$("#reviews").paginate({
			"items_per_page":2
		});
	};
	//Render Rating
	self.renderRating = function(targetElement,customerRating){
		customerRating = Math.ceil(customerRating);
		//Clear the deck
		$(targetElement).empty();
		//Loop
		for(i = 0; i < customerRating; i++){
			$(targetElement).append("<i class='fa fa-star fa-lg'></i>");
		}
	};
	//Showtime
	$(document).ready(function(){
		self.constructor();
	});
}