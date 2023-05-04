# This file is part of Jeedom.
#
# Jeedom is free software: you can redistribute it and/or modify
# it under the terms of the GNU General Public License as published by
# the Free Software Foundation, either version 3 of the License, or
# (at your option) any later version.
# 
# Jeedom is distributed in the hope that it will be useful,
# but WITHOUT ANY WARRANTY; without even the implied warranty of
# MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
# GNU General Public License for more details.
# 
# You should have received a copy of the GNU General Public License
# along with Jeedom. If not, see <http://www.gnu.org/licenses/>.

def value_to_sf(value):
  if not isinstance(value, (int, float)) or value == 0:
    return (0, 0)
  
  s = str(value).rstrip('0')
  if '.' in s:
    sf = len(s.split('.')[1]) * -1
    val = value * 10 ** (sf * -1)
  else:
    val, sf = value, 0
    while val % 10 == 0:
      sf += 1
      val //= 10
  
  return (int(val), sf)
