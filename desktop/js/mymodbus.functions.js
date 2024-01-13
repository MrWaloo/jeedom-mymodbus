/* This file is part of Jeedom.
 *
 * Jeedom is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * Jeedom is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with Jeedom. If not, see <http://www.gnu.org/licenses/>.
 */

// Namespace
mymodbus = {}

// Send ajax request to MyModbus plugin
mymodbus.callPluginAjax = function(_params) {
  $.ajax({
    async: _params.async == undefined ? true : _params.async,
    global: false,
    type: "POST",
    url: "plugins/mymodbus/core/ajax/mymodbus.ajax.php",
    data: _params.data,
    dataType: 'json',
    error: function (request, status, error) {
      handleAjaxError(request, status, error);
    },
    success: function (data) {
      if (data.state != 'ok') {
        $.fn.showAlert({message: data.result, level: 'danger'});
      }
      else {
        if (typeof _params.success === 'function') {
          _params.success(data.result);
        }
      }
    }
  });
}

