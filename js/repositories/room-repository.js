(function() {
    'use strict';

    const BaseRepository = window.LocalBase.repositories.Repository;

    /** Zweck: Kapselt Raum-, Buchungs- und Monatsendpunkte hinter dem gemeinsamen LocalBase-Repositoryvertrag. */
    class RoomRepository extends BaseRepository {
        async month(month) {
            const data = await this.request(`/api/month/${this.encode(month)}`);
            return {
                ...data,
                rooms: window.FlzRoom.Room.get_all(data.rooms),
                bookings: window.FlzRoom.Booking.get_all(data.bookings),
            };
        }

        createBooking(payload) {
            return this.post('/api/bookings', payload);
        }

        updateBooking(id, payload) {
            return this.request(`/api/bookings/${this.encode(id)}`, { method: 'PUT', body: JSON.stringify(payload) });
        }

        deleteBooking(id, payload = {}) {
            return this.request(`/api/bookings/${this.encode(id)}`, { method: 'DELETE', body: JSON.stringify(payload) });
        }

        createRoom(payload) {
            return this.post('/api/rooms', payload);
        }

        updateRoom(id, payload) {
            return this.request(`/api/rooms/${this.encode(id)}`, { method: 'PUT', body: JSON.stringify(payload) });
        }

        deleteRoom(id) {
            return this.request(`/api/rooms/${this.encode(id)}`, { method: 'DELETE' });
        }
    }

    window.FlzRoom = window.FlzRoom || {};
    window.FlzRoom.RoomRepository = RoomRepository;
}());
