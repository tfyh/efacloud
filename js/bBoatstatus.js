var bBoatstatus = {

	AVAILABLE : "verfügbar",
	BOOKED : "reserviert",
	NOTAVAILABLE : "nicht verfügbar",
	HIDE : "nicht anzeigen",
	ONTHEWATER : "unterwegs",

	// gets the availability for panel display out of base status, current
	// status and ShowInList values.
	isAvailable : function(boatstatusRecord) {
		var baseStatusAvailable = (boatstatusRecord.BaseStatus && 
				(boatstatusRecord.BaseStatus.localeCompare("AVAILABLE") == 0));
		var currentStatusAvailable = (boatstatusRecord.CurrentStatus &&
				(boatstatusRecord.CurrentStatus.localeCompare("AVAILABLE") == 0));
		var ShowInListAvailable = !boatstatusRecord.ShowInList
				|| (boatstatusRecord.ShowInList && (boatstatusRecord.ShowInList.localeCompare("AVAILABLE") == 0));
		return baseStatusAvailable && currentStatusAvailable && ShowInListAvailable;
	},
	
	// gets the availability for panel display out of base status, current
	// status and ShowInList values.
	isOnTheWater : function(boatstatusRecord) {
		var currentStatusOnTheWater = (boatstatusRecord.CurrentStatus &&
				(boatstatusRecord.CurrentStatus.localeCompare("ONTHEWATER") == 0));
		var ShowInListOnTheWater = (boatstatusRecord.ShowInList &&
		 		(boatstatusRecord.ShowInList.localeCompare("ONTHEWATER") == 0));
		return currentStatusOnTheWater || ShowInListOnTheWater;
	},
	
	// get a predominat status
	statusToUse : function(boatstatusRecord) {
		if (!boatstatusRecord)
			return "NOTAVAILABLE";
		if ((boatstatusRecord.BaseStatus && (boatstatusRecord.BaseStatus.localeCompare("HIDE") == 0)) || 
				(boatstatusRecord.CurrentStatus && (boatstatusRecord.CurrentStatus.localeCompare("HIDE") == 0)) || 
				(boatstatusRecord.ShowInList && (boatstatusRecord.ShowInList.localeCompare("HIDE") == 0)))
				return "HIDE";
		if ((boatstatusRecord.CurrentStatus && (boatstatusRecord.CurrentStatus.localeCompare("ONTHEWATER") == 0)) || 
				(boatstatusRecord.ShowInList && (boatstatusRecord.ShowInList.localeCompare("ONTHEWATER") == 0)))
				return "ONTHEWATER";
		if ((boatstatusRecord.BaseStatus && (boatstatusRecord.BaseStatus.localeCompare("NOTAVAILABLE") == 0)) || 
				(boatstatusRecord.CurrentStatus && (boatstatusRecord.CurrentStatus.localeCompare("NOTAVAILABLE") == 0)) || 
				(boatstatusRecord.ShowInList && (boatstatusRecord.ShowInList.localeCompare("NOTAVAILABLE") == 0)))
				return "NOTAVAILABLE";
		return "AVAILABLE";
	}
}