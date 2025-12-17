/**
 *
 *       efaCloud
 *       --------
 *       https://www.efacloud.org
 *
 * Copyright  2018-2024  Martin Glade
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 *    http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */

var oBoatstatus = {
		
	locValues : false,

	// the status to display. Must not be part of the initializer, because i18n information is 
	// not available at initialiszation.
	locValue : function(enValue) {
		if (!oBoatstatus.locValues)
			oBoatstatus.locValues = {
				AVAILABLE : _("A5JJSu|available"),
				BOOKED : _("iQkZZG|reserved"),
				NOTAVAILABLE : _("zP6A0Y|not available"),
				HIDE : _("SEZB6S|do not show"),
				ONTHEWATER : _("3skGH2|en route")
			};
		return oBoatstatus.locValues[enValue];
	},

	// gets the availability for panel display out of base status, current
	// status and ShowInList values.
	isAvailable : function(boatstatusRecord) {
		if (!boatstatusRecord)
			return false;
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
		if (!boatstatusRecord)
			return false;
		var currentStatusOnTheWater = (boatstatusRecord.CurrentStatus) ?
				(boatstatusRecord.CurrentStatus.localeCompare("ONTHEWATER") == 0) : false;
		var ShowInListOnTheWater = (boatstatusRecord.ShowInList) ?
				(boatstatusRecord.ShowInList.localeCompare("ONTHEWATER") == 0) : false;
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
		if (!boatstatusRecord.BaseStatus || !boatstatusRecord.CurrentStatus || !boatstatusRecord.BaseStatus)
			// some data error, do not show
			return "HIDE";
		return "AVAILABLE";
	}
}
